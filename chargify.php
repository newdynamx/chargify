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
    public $creditCardPercentFee = 2.9;
    public $creditCardFee = .30;

    public $dataByDate = [];

    public $weekTotals = [];

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

                $date = date('D Y-m-d', strtotime($data[19] .  '3 days'));
                $day = date("l", strtotime($data[19] .  '3 days'));

                switch ($day) {
                    case "Saturday":
                        $date = date('D Y-m-d', strtotime($data[19] .  '5 days'));
                        break;
                    case "Sunday":
                        $date = date('D Y-m-d', strtotime($data[19] .  '4 days'));
                }

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
        $num = 0;
        $thisWeek = '';
        foreach($this->dataByDate as $key => $data)
        {
            $week = date('W', strtotime($data['date']));
            $day = date('l', strtotime($data['date']));

            if ($thisWeek === '' || $week != $thisWeek) {
                $thisWeek = $week;

                if ($day != 'Monday') {
                    $dateRangeStart = date('D Y-m-d', strtotime('last Monday', strtotime($data['date'])));
                } else {
                    $dateRangeStart = date('D Y-m-d', strtotime($data['date']));
                }

                if ($day != 'Friday') {
                    $dateRangeEnd = date('D Y-m-d', strtotime('this Friday', strtotime($data['date'])));
                } else {
                    $dateRangeEnd = date('D Y-m-d', strtotime($data['date']));
                }
                $num++;

                $dateRange = $dateRangeStart . " - " . $dateRangeEnd;
            }
            if (!isset($data['price'])) {
                $data['price'] = 0;
            }

            if (!isset($this->weekTotals[$dateRange]['price'])) {
                $this->weekTotals[$dateRange]['price'] = 0;
            }
            $this->weekTotals[$dateRange]['price'] += $this->removeCreditCardFee($data['price']);
        }
    }

    public function removeCreditCardFee($amount)
    {
        $fee = bcadd(bcdiv(bcmul($amount, $this->creditCardPercentFee, 2), 100, 2), $this->creditCardFee, 2);
        return bcsub($amount, $fee, 2);
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
                        <td><?php echo $key; ?></td>
                        <td align="right">$<?php echo number_format($value['price'], 2, '.', ','); ?></td>
                    </tr>
                <?php } ?>
            </table>

            <button onclick="download_file('<?php echo $chargify->createdFilename; ?>')">Download CSV</button>
        <?php } ?>
    </body>
</html>
