<?php
if (isset($_FILES['csv_file'])) {
    $chargify = new chargify();
    $chargify->importCsv();
    $chargify->readCsvFile();
    $chargify->getWeekTotalsFromData();
    $chargify->createCsv();
}

if (isset($_REQUEST['download']) && $_REQUEST['download'] == 'download') {
    getFileName();
}

if (isset($_REQUEST['filenameDownload']) && $_REQUEST['filenameDownload'] != '') {
    downloadFileInIFrame();
}

class chargify
{
    public $dataByDate = [];

    public $weekTotals;

    public $filename;

    public $createdFilename;

    public function importCsv()
    {
        $filename = "csv/" . $_FILES["csv_file"]["name"];

        if(!move_uploaded_file($_FILES["csv_file"]["tmp_name"], $filename)){
            return("Error uploading file.");
        }

        $this->filename = $filename;
    }

    public function readCsvFile()
    {
        $this->dataByDate = [];
        $num = 1;
        if (($handle = fopen($this->filename, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if($num == 1) {
                    $num++;
                    continue; //skip headers
                }

                //state
                if(isset($data[5]) && $data[5] != 'active') {
                    continue;
                }

                //date
                if(!isset($data[19]) || $data[19] == '') {
                    continue;
                }

                $date = date('D Y-m-d', strtotime($data[19] .  '2 days'));

                $this->dataByDate[] = [
                    'date' => $date,
                    'price' => floatval($data[9])
                ];
            }
        }

        usort($this->dataByDate, "date_compare");
    }

    public function getWeekTotalsFromData()
    {
        $final = [];
        $num = 0;
        foreach($this->dataByDate as $key => $data)
        {
            $week = date('W', strtotime($data['date']));
            $day = date('N', strtotime($data['date']));

            if($day > 5) {
                $week++;
            }

            if(!isset($thisWeek)) {
                $thisWeek = $week;
                $final[$num]['date'] = date('D Y-m-d', strtotime('this Monday', strtotime($data['date']))) . " - " .
                    date('D Y-m-d', strtotime('this Friday', strtotime($data['date'])));
            }

            if(!isset($thisDate)) {
                $thisDate = $data['date'];
            }

            if (($week > $thisWeek) && (isset($flag) && $flag == false && $thisDate != $data['date'])) {
                $num++;
                $thisWeek = $week;
                $final[$num]['date'] = date('D Y-m-d', strtotime('this Monday', strtotime($data['date']))) . " - " .
                    date('D Y-m-d', strtotime('this Friday', strtotime($data['date'])));
                $flag = true;
            }else{
                $flag = false;
                $thisDate = $data['date'];
            }

            if(!isset($final[$num]['price'])) {
                $final[$num]['price'] = floatval(0);
            }

            $final[$num]['price'] = number_format(bcadd($final[$num]['price'], $data['price'], 2), 2, '.', ',');
        }

        $this->weekTotals = $final;
    }

    public function createCsv()
    {
        $date = date('ymdis');
        $this->createdFilename = $date."_Chargify.csv";
        $fp = fopen("createdCsv/".$this->createdFilename, 'w');
        $headers = ['Date Range', 'Total Amount'];
        fputcsv($fp, $headers);

        foreach ($this->weekTotals as $fields) {
            fputcsv($fp, $fields);
        }

        fclose($fp);
    }

}

function date_compare($a, $b)
{
    $t1 = strtotime($a['date']);
    $t2 = strtotime($b['date']);
    return $t1 - $t2;
}

function getFileName()
{
    echo $_REQUEST['filename'];
    exit;
}

function downloadFileInIFrame()
{
    header("Cache-Control: public");
    header("Content-Description: File Transfer");
    header("Content-Disposition: attachment; filename=".$_REQUEST['filenameDownload']);
    header("Content-Type: text/csv");

    readfile("createdCsv/".$_REQUEST['filenameDownload']);
    exit;
}
?>

<html>
    <head>
        <title>Chargify</title>
        <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
        <script>
            function download_file(fileName)
            {
                var argObj = new Object;
                argObj.download = "download";
                argObj.filename = fileName;

                $.post("chargify.php",argObj,function(data){
                    //create hidden iframe to download xml file
                    var elemIF = document.createElement("iframe");
                    elemIF.src = "chargify.php?filenameDownload="+data;
                    elemIF.style.display = "none";
                    document.body.appendChild(elemIF);
                });


            }
        </script>
    </head>
    <body>
        <form id="csv_upload" name="csv_upload" action="" enctype="multipart/form-data" method="post">
            <input id="'csv_file" name="csv_file" type="file" />
            <br />
            <input type="submit" value="Submit" />
        </form>

        <?php if (isset($chargify->weekTotals)) { ?>
            <table id="totals" width="25%">
                <tr>
                    <th>Date Range</th>
                    <th>Total Amount</th>
                </tr>
                <?php foreach($chargify->weekTotals as $key => $value) { ?>
                    <tr>
                        <td><?php echo $value['date']; ?></td>
                        <td align="right">$<?php echo $value['price']; ?></td>
                    </tr>
                <?php } ?>
            </table>

            <button onclick="download_file('<?php echo $chargify->createdFilename; ?>')">Download CSV</button>
        <?php } ?>
    </body>
</html>
