<?php

namespace Tests\Browser;

use Symfony\Component\DomCrawler\Crawler;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use GuzzleHttp\Client;

class ExampleTest extends DuskTestCase
{
    protected $key;

    /**
     * A basic browser test example.
     *
     * @return void
     */
    public function testBasicExample()
    {

        $client         = new Client(); //create a new customer of GuzzleHttp
        $page           = 0; //Current Page
        $hivepacket     = 0; //counter for hive package
        $hivePacketSize = 1000; //size hive packge
        $processes      = []; //list of processes
        $this->getKey(); // save mkey secop in protected key variable

        while ($page < 43301) { //cycle limit

            $start      = 5 * $page;
            $end        = (5 * ($page + 1)) - 1;
            $startIndex = 5 * $page;
            $endIndex   = (5 * ($page + 1)) - 1;
            dump('page: '.$page, 'start: '.$start.'end: '.$end, 'StartIndex:'.$startIndex, 'endIndex'.$endIndex);
            try {
                //Request
                $request = $client->request(
                    'post',
                    'https://community.secop.gov.co/Public/Tendering/ContractNoticeManagement/ResultListGoToPage?mkey='.$this->key,
                    [
                        'headers'         => [
                            'Accept' => 'application/json',
                        ],
                        'form_params'     => [
                            'startIdx'                 => $start,
                            'endIdx'                   => $end,
                            'pageNumber'               => $page,
                            'startIndex'               => $startIndex,
                            'startIndex'               => $endIndex,
                            'perspective'              => 'All',
                            'initAction'               => 'Index',
                            'categorizationSystemCode' => 'UNSPSC',
                            'orderParam'               => 'RequestOnlinePublishingDateDESC',
                        ],
                        'connect_timeout' => 30,
                    ]
                );
                //html full page
                $htmlPage = $request->getBody()->getContents();
                //GetData return array list processes from html
                $processes = array_merge($processes, $this->getData($htmlPage));

                if ($hivepacket == $hivePacketSize) { //saves package
                    $sqlText    = $this->generateSql($processes);
                    $processes  = [];
                    $hivepacket = 0;
                    $this->saveFile($sqlText, $page); //saves package in directory \hiveSql
                    $this->save($sqlText); //saves package in hive
                }
            } catch (\Exception $e) {
                error_log($e->getMessage());
                error_log('exepcion capturada');
            }
            $hivepacket++;
            $page++;
        }
    }

    public function generateSql($arrayInfo)
    {
        $sqlText = "INSERT INTO procesos (Entidad,Referencia,Descripcion,FaseActual,FechaPublicacion,FechaPresentacionOfertas,Cuantia,Estado,Link) VALUES ";
        foreach ($arrayInfo as $row) {
            if (count($row) > 0) {
                if (isset($row['Entidad'])) {
                    $sqlText .= "('".$row['Entidad']."','".$row['Referencia']."','".$row['Descripcion']."','".$row['FaseActual']."','".$row['FechaPublicacion']."','".$row['FechaPresentacionOfertas']."','".$row['Cuantia']."','".$row['Estado']."','".$row['link']."'),";
                }
            }
        }
        $sqlText = substr(trim($sqlText), 0, -1);

        return $sqlText;
    }

    public function save($sqlText)
    {
        //dump($sqlText);die;
        $conn = $this->conexion();
        $sql  = <<<EOF
        $sqlText
EOF;
        //    "";
        $result = odbc_exec($conn, $sql);

        // $this->getProcess($conn);

        return $result;
    }

    public function conexion()
    {
        $conn = odbc_connect('hivedriver', 'hiveuser', 'hivepasss');
        if ( ! $conn) {
            exit("Connection to the database Failed: ".$conn);
            die;
        }

        return $conn;
    }

    public function getProcess($conn)
    {
        $result = odbc_exec($conn, 'select * from procesos');
        while ($row = odbc_fetch_array($result))  // have tried odbc_fetch_row already
        {
            dump(odbc_result($result, 1));
        }
    }

    public function getData($htmlPage)
    {
        $crawler   = new Crawler($htmlPage);
        $resultado = $crawler->filter('table')->filter('tr')->each(
            function ($tr) {
                $tds      = [];
                $tdsFinal = [];
                $td       = $tr->filter('td')->each(
                    function ($td) {

                        $span = $td->filter('span')->each(
                            function ($span) {
                                $text = $span->text();
                                if ($text != '') {
                                    return trim(str_replace("'", '', $text));
                                }
                            }
                        );
                        $a    = $td->filter('a')->each(
                            function ($a) {
                                $url      = null;
                                $a        = $a->attr('onclick');
                                $aExplode = explode('+', $a);
                                if (isset($aExplode[3])) {
                                    $url       = 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=';
                                    $noticeUID = trim(str_replace("'", '', $aExplode[3]));
                                    $url       = $url.$noticeUID;
                                }

                                return $url;
                            }
                        );

                        return array_merge($span, $a);
                    }
                );
                foreach ($td as $tdAux) {
                    foreach ($tdAux as $t) {
                        if ($t != '') {
                            array_push($tds, trim($t));
                        }
                    }
                }
                if (count($tds) > 1 && count($tds) < 11) {
                    $tdsFinal['Entidad']                  = ($tds[0] == "" ? "" : $tds[0]);
                    $tdsFinal['Referencia']               = ($tds[1] == "" ? "" : $tds[1]);
                    $tdsFinal['Descripcion']              = ($tds[2] == "" ? "" : $tds[2]);
                    $tdsFinal['FaseActual']               = ($tds[3] == "" ? "" : $tds[3]);
                    $tdsFinal['FechaPublicacion']         = ($tds[4] == "" ? "" : $tds[4]);
                    $tdsFinal['FechaPresentacionOfertas'] = ($tds[5] == "" ? "" : $tds[5]);
                    $tdsFinal['Cuantia']                  = ($tds[7] == "" ? "" : $tds[7]);
                    $tdsFinal['Estado']                   = ($tds[8] == "" ? "" : $tds[8]);
                    $tdsFinal['link']                     = ($tds[9] == "" ? "" : $tds[9]);
                    error_log(print_r($tdsFinal['FechaPublicacion'], true));
                } elseif (count($tds) > 1) {
                    $tdsFinal['Entidad']                  = ($tds[0] == "" ? "" : $tds[0]);
                    $tdsFinal['Referencia']               = ($tds[1] == "" ? "" : $tds[1]);
                    $tdsFinal['Descripcion']              = ($tds[2] == "" ? "" : $tds[2]);
                    $tdsFinal['FaseActual']               = ($tds[3] == "" ? "" : $tds[3]);
                    $tdsFinal['FechaPublicacion']         = ($tds[4] == "" ? "" : $tds[4]);
                    $tdsFinal['FechaPresentacionOfertas'] = ($tds[5] == "" ? "" : $tds[5]);
                    $tdsFinal['Cuantia']                  = ($tds[8] == "" ? "" : $tds[8]);
                    $tdsFinal['Estado']                   = ($tds[9] == "" ? "" : $tds[9]);
                    $tdsFinal['link']                     = ($tds[10] == "" ? "" : $tds[10]);
                    error_log(print_r($tdsFinal['FechaPublicacion'], true));
                }

                return $tdsFinal;
            }
        );

        return $resultado;
    }

    public function getKey()
    {
        //Open Browse
        $this->browse(
            function (Browser $browser) {

                //Login
                $browser->visit('https://community.secop.gov.co/STS/Users/Login/Index')->type(
                    '#txtUserName',
                    'Luisk262'
                )->type('#txtPassword', 'Secop2/CO2')->click('#btnLoginButton')->pause(5000)->visit(
                    'https://community.secop.gov.co/Public/Tendering/ContractNoticeManagement/Index'
                )->pause(1000);

                //identify the cells in the table
                $element = $browser->element(
                    '#tblMainTable_trRowMiddle_tdCell1_tblForm_trGridRow_tdCell1_grdResultList_Paginator > tbody > tr > td'
                );
                //Return html from table
                $html = $element->getAttribute('innerHTML');

                //extract the variable mkey
                $text      = explode('\', {', $html);
                $mkey      = explode('mkey=', $text[0])[1];
                $this->key = $mkey;
                error_log('key: '.$mkey);
            }
        );
        //close browse
        self::closeAll();
    }

    public function saveFile($text, $i)
    {
        $file = 'hiveSql/hive'.$i.'.sql';
        if ( ! is_file($file)) {
            file_put_contents($file, $text);
        }
    }
}
