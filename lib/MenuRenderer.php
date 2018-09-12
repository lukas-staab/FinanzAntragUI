<?php
/**
 * Created by PhpStorm.
 * User: lukas
 * Date: 03.02.18
 * Time: 14:43
 */

class MenuRenderer extends Renderer{
    const DEFAULT = "mygremium";
    
    private $pathinfo;
    
    public function __construct($pathinfo = []){
        if (!isset($pathinfo) || empty($pathinfo) || !isset($pathinfo["action"])){
            $pathinfo["action"] = self::DEFAULT;
        }
        $this->pathinfo = $pathinfo;
    }
    
    public function render(){
        $attributes = (AUTH_HANDLER)::getInstance()->getAttributes();
        
        switch ($this->pathinfo["action"]){
            case "mygremium":
            case "allgremium":
                if ($this->pathinfo["action"] === "allgremium")
                    $gremien = $attributes["alle-gremien"];
                else
                    $gremien = $attributes["gremien"];
                
                $gremien = array_filter($gremien, function($val){
                    global $GremiumPrefix;
                    foreach ($GremiumPrefix as $prefix){
                        if (substr($val, 0, strlen($prefix)) === $prefix){
                            return true;
                        }
                    }
                    return false;
                });
                rsort($gremien, SORT_STRING | SORT_FLAG_CASE);
                $gremien[] = "";
                HTMLPageRenderer::registerProfilingBreakpoint("start-rendering");
                //print_r($this->pathinfo["action"]);
                $this->renderProjekte($gremien);
                break;
            case "mykonto":
                $this->renderMyProfile();
                break;
            case "stura":
                $this->renderStuRaView();
                break;
            case "hv":
                $this->renderHVView();
                break;
            case "kv":
                $this->renderKVView();
                break;
            case "exportBank":
                $this->renderExportBank();
                break;
            case "booking":
                (AUTH_HANDLER)::getInstance()->requireGroup(HIBISCUSGROUP);
                $this->renderBooking();
                //TODO: FIXME!;
                break;
            case "check-booking":
                (AUTH_HANDLER)::getInstance()->requireGroup(HIBISCUSGROUP);
                $this->renderBookingCheck();
                break;
            case "konto":
                (AUTH_HANDLER)::getInstance()->requireGroup(HIBISCUSGROUP);
                $this->renderKonto();
                break;
            case "save-booking":
                $this->saveBooking();
                break;
            //fall through
            case "booking-history":
                $this->renderBookingHistory();
                break;
            default:
                ErrorHandler::_errorExit("{$this->pathinfo['action']} kann nicht interpretiert werden");
                break;
        }
    }
    
    public function renderProjekte($gremien){
        //$enwuerfe = DBConnector::getInstance()->dbFetchAll("antrag",["state" => "draft","creator" => (AUTH_HANDLER)::getInstance()->getUserName()]);
        //$projekte = DBConnector::getInstance()->getProjectFromGremium($gremien, "projekt-intern");
        $projekte = DBConnector::getInstance()->dbFetchAll(
            "projekte",
            [DBConnector::FETCH_ASSOC, DBConnector::FETCH_GROUPED],
            [
                "org",
                "projekte.*",
                "ausgaben" => ["projektposten.ausgaben", DBConnector::GROUP_SUM_ROUND2],
                "einnahmen" => ["projektposten.einnahmen", DBConnector::GROUP_SUM_ROUND2],
            ],
            ["org" => ["in", $gremien]],
            [
                ["table" => "projektposten", "type" => "left", "on" => ["projektposten.projekt_id", "projekte.id"]],
            ],
            ["org" => true],
            ["id"]
        );
        $pids = [];
        array_walk($projekte, function($array, $gremien) use (&$pids){
            array_walk($array, function($res, $key) use (&$pids){
                $pids[] = $res["id"];
            });
        });
        $auslagen = DBConnector::getInstance()->dbFetchAll(
            "auslagen",
            [DBConnector::FETCH_ASSOC, DBConnector::FETCH_GROUPED],
            [
                "projekt_id",  // group idx
                "projekt_id", "auslagen.id", "name_suffix", //auslagen Link
                "zahlung-name", // Empf. Name
                "einnahmen" => ["einnahmen", DBConnector::GROUP_SUM_ROUND2],
                "ausgaben" => ["ausgaben", DBConnector::GROUP_SUM_ROUND2],
                "state"
            ],
            ["projekt_id" => ["IN", $pids]],
            [
                ["table" => "belege", "type" => "LEFT", "on" => ["belege.auslagen_id", "auslagen.id"]],
                ["table" => "beleg_posten", "type" => "LEFT", "on" => ["beleg_posten.beleg_id", "belege.id"]],
            ],
            ["id" => true],
            ["auslagen_id"]
        );
        
        //FIXME: do later :)
        /*if ((AUTH_HANDLER)::getInstance()->hasGroup("ref-finanzen")){
            $extVereine = ["Bergfest.*", ".*KuKo.*", ".*ILSC.*", "Market Team.*", ".*Second Unit Jazz.*", "hsf.*", "hfc.*", "FuLM.*", "KSG.*", "ISWI.*"]; //TODO: From external source
            $ret = DBConnector::getInstance()->getProjectFromGremium($extVereine, "extern-express");
            if ($ret !== false){
                //var_dump($ret);
                $projekte = array_merge($projekte, $ret);
            }
        }*/
        
        //var_dump(end(end($projekte)));
        ?>
        <div class="panel-group" id="accordion">
            <?php $i = 0;
            if (isset($projekte) && !empty($projekte) && $projekte){
                foreach ($projekte as $gremium => $inhalt){
                    if (count($inhalt) == 0) continue; ?>
                    <div class="panel panel-default">
                        <div class="panel-heading collapsed" data-toggle="collapse" data-parent="#accordion"
                             href="#collapse<?php echo $i; ?>">
                            <h4 class="panel-title">
                                <i class="fa fa-fw fa-togglebox"></i>&nbsp;<?= empty($gremium) ? "Nicht zugeordnete Projekte" : $gremium ?>
                            </h4>
                        </div>
                        <div id="collapse<?php echo $i; ?>" class="panel-collapse collapse">
                            <div class="panel-body">
                                <?php $j = 0; ?>
                                <div class="panel-group" id="accordion<?php echo $i; ?>">
                                    <?php foreach ($inhalt as $projekt){
                                        $id = $projekt["id"];
                                        $year = date("y", strtotime($projekt["createdat"])); ?>
                                        <div class="panel panel-default">
                                            <div class="panel-link"><?= generateLinkFromID("IP-$year-$id", "projekt/" . $id) ?>
                                            </div>
                                            <div class="panel-heading collapsed <?= (!isset($auslagen[$id]) || count($auslagen[$id]) === 0) ? "empty" : "" ?>"
                                                 data-toggle="collapse" data-parent="#accordion<?php echo $i ?>"
                                                 href="#collapse<?php echo $i . "-" . $j; ?>">
                                                <h4 class="panel-title">
                                                    <i class="fa fa-togglebox"></i><span
                                                            class="panel-projekt-name"><?= $projekt["name"] ?></span>
                                                    <span class="panel-projekt-money text-muted hidden-xs"><?= number_format($projekt["ausgaben"], 2, ",", ".") ?></span>
                                                    <span class="label label-info project-state-label"><?= ProjektHandler::getStateString($projekt["state"]) ?></span>
                                                </h4>
                                            </div>
                                            <?php if (isset($auslagen[$id]) && count($auslagen[$id]) > 0){ ?>
                                                <div id="collapse<?php echo $i . "-" . $j; ?>"
                                                     class="panel-collapse collapse">
                                                    <div class="panel-body">
                                                        <?php
                                                        $sum_a_in = 0;
                                                        $sum_a_out = 0;
                                                        $sum_e_in = 0;
                                                        $sum_e_out = 0;
                                                        foreach ($auslagen[$id] as $a){
                                                            if (substr($a['state'], 0, 6) == 'booked' || substr($a['state'], 0, 10) == 'instructed'){
                                                                $sum_a_in += $a['einnahmen'];
                                                                $sum_a_out += $a['ausgaben'];
                                                            }
                                                            if (substr($a['state'], 0, 10) != 'revocation' && substr($a['state'], 0, 5) != 'draft'){
                                                                $sum_e_in += $a['einnahmen'];
                                                                $sum_e_out += $a['ausgaben'];
                                                            }
                                                        }
                                                        
                                                        $this->renderTable(
                                                            ["Name", "Zahlungsempfänger", "Einnahmen", "Ausgaben", "Status"],
                                                            [$auslagen[$id]],
                                                            [
                                                                [$this, "auslagenLinkEscapeFunction"], // 3 Parameter
                                                                null,  // 1 parameter
                                                                [$this, "moneyEscapeFunction"],
                                                                [$this, "moneyEscapeFunction"],
                                                                function($stateString){
                                                                    $text = AuslagenHandler2::getStateString(AuslagenHandler2::state2stateInfo($stateString)['state']);
                                                                    return "<div class='label label-info'>$text</div>";
                                                                }

                                                            ],
                                                            [
                                                                [
                                                                    '',
                                                                    'Eingereicht:',
                                                                    '&Sigma;: ' . number_format($sum_e_in, 2) . '&nbsp€',
                                                                    '&Sigma;: ' . number_format($sum_e_out, 2) . '&nbsp€',
                                                                    '&Delta;: ' . number_format($sum_e_out - $sum_e_in, 2) . '&nbsp€',
                                                                ],
                                                                [
                                                                    '',
                                                                    'Ausgezahlt:',
                                                                    '&Sigma;: ' . number_format($sum_a_in, 2) . '&nbsp€',
                                                                    '&Sigma;: ' . number_format($sum_a_out, 2) . '&nbsp€',
                                                                    '&Delta;: ' . number_format($sum_a_out - $sum_a_in, 2) . '&nbsp€',
                                                                ]
                                                            ]
                                                        ); ?>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                        
                                        <?php $j++;
                                    } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    $i++;
                }
            }else{ ?>
                <h2>Bisher wurden leider noch keine Projekte angelegt. :(</h2>
            <?php } ?>
        </div>
        
        <?php
    }
    
    public function renderMyProfile(){
        
        $user = DBConnector::getInstance()->getUser();
        if (isset($user["iban"])){
            $iban = $user["iban"];
        }else{
            $iban = "";
        }
        ?>

        <form id="editantrag" role="form" action="<?= $_SERVER["PHP_SELF"]; ?>" method="POST"
              enctype="multipart/form-data" class="ajax">
            <input type="hidden" name="action" value="mykonto.update"/>
            <input type="hidden" name="nonce" value="<?= $GLOBALS["nonce"]; ?>"/>
            <?php //renderForm($form);
            ?>
            <a href="javascript:void(false);" class='btn btn-success submit-form validate pull-right' data-name="iban"
               data-value="">Speichern</a>
        </form>
        
        <?php
    }
    
    private function renderStuRaView(){
        $header = ["Projekte", "Organisation", "Projektbeginn", /*"Einnahmen", "Ausgaben"*/];
        
        //TODO: also externe Anträge
        // $groups[] = ["name" => "Externe Anträge", "fields" => ["type" => "extern-express", "state" => "need-stura",]];
        list($header, $internContent, $escapeFunctions) = $this->fetchProjectsWithState("need-stura");
        list(, $internContentHV,) = $this->fetchProjectsWithState("ok-by-hv");
        $groups = [
            "Vom StuRa abzustimmen" => $internContent,
            "zur Verkündung (genehmigt von HV)" => $internContentHV,
        ];
        $this->renderHeadline("Projekte für die nächste StuRa Sitzung");
        $this->renderTable($header, $groups, $escapeFunctions);
    }
    
    /**
     * @param $statestring
     *
     * @return array [$header, $dbres, $escapeFunctions]
     */
    private function fetchProjectsWithState($statestring){
        $header = ["Projekt", "Organisation", "Einnahmen", "Ausgaben", "Projektbeginn"];
        $dbres = DBConnector::getInstance()->dbFetchAll(
            "projekte",
            [DBConnector::FETCH_NUMERIC],
            [
                "projekte.id", "createdat", "projekte.name",
                "org",
                "einnahmen" => ["projektposten.einnahmen", DBConnector::GROUP_SUM_ROUND2],
                "ausgaben" => ["projektposten.ausgaben", DBConnector::GROUP_SUM_ROUND2],
                "createdat",
            ],
            ["state" => "$statestring"],
            [["type" => "inner", "table" => "projektposten", "on" => ["projektposten.projekt_id", "projekte.id"]]],
            ["date-start" => true],
            ["projekte.id"]
        );
        $escapeFunctionsIntern = [
            [$this, "projektLinkEscapeFunction"],
            null,
            [$this, "moneyEscapeFunction"],
            [$this, "moneyEscapeFunction"],
            [$this, "date2relstrEscapeFunction"],
        ];
        return [$header, $dbres, $escapeFunctionsIntern];
    }
    
    private function renderHVView(){
    
        //Projekte -------------------------------------------------------------------------------------------------
        list($headerIntern, $internWIP, $escapeFunctionsIntern) = $this->fetchProjectsWithState("wip");
        $groupsIntern["zu prüfende Interne Projekte"] = $internWIP;
    
        //Auslagenerstattungen -------------------------------------------------------------------------------------
        list($headerAuslagen, $auslagenWIP, $escapeFunctionsAuslagen) = $this->fetchAuslagenWithState("wip", "hv");
        $groupsAuslagen["Auslagenerstattungen HV fehlt"] = $auslagenWIP;
        list(, $auslagenWIP,) = $this->fetchAuslagenWithState("wip", "belege");
        $groupsAuslagen["Auslagenerstattungen Belege fehlen"] = $auslagenWIP;
    
        //TODO: Implementierung vom rest
        //$groups[] = ["name" => "Externe Projekte für StuRa Situng vorbereiten", "fields" => ["type" => "extern-express", "state" => "draft"]];
    
        $this->renderHeadline("Von den Haushaltsverantwortlichen zu erledigen");
        $this->renderTable($headerIntern, $groupsIntern, $escapeFunctionsIntern);
        $this->renderTable($headerAuslagen, $groupsAuslagen, $escapeFunctionsAuslagen);
    }
    
    /**
     * @param $stateString
     * @param $missingColumn string  can be: hv, kv, belege
     *
     * @return array [$header, $auslagen, $escapeFunctionAuslagen]
     */
    private function fetchAuslagenWithState($stateString, $missingColumn){
        $headerAuslagen = ["Projekt", "Auslage", "Organisation", "Einnahmen", "Ausgaben", "zuletzt geändert"];
        $auslagen = DBConnector::getInstance()->dbFetchAll(
            "auslagen",
            [DBConnector::FETCH_NUMERIC],
            [
                "projekte.id", "createdat", "name", //Projekte Link
                "projekte.id", "auslagen.id", "auslagen.name_suffix", // Auslagen Link
                "projekte.org", // Org
                "einnahmen" => ["beleg_posten.einnahmen", DBConnector::GROUP_SUM_ROUND2],
                "ausgaben" => ["beleg_posten.ausgaben", DBConnector::GROUP_SUM_ROUND2],
                "last_change"  // letzte änderung
            ],
            [
                "auslagen.state" => ["LIKE", "$stateString%"],
                "auslagen.ok-$missingColumn" => "",
            ],
            [
                ["table" => "projekte", "type" => "inner", "on" => ["projekte.id", "auslagen.projekt_id"]],
                ["table" => "belege", "type" => "inner", "on" => ["belege.auslagen_id", "auslagen.id"]],
                ["table" => "beleg_posten", "type" => "inner", "on" => ["belege.id", "beleg_posten.beleg_id"]],
            ],
            ["last_change" => true],
            ["auslagen.id"]
        );
        $escapeFunctionsAuslagen = [
            [$this, "projektLinkEscapeFunction"],
            [$this, "auslagenLinkEscapeFunction"],
            null,
            [$this, "moneyEscapeFunction"],
            [$this, "moneyEscapeFunction"],
            [$this, "date2relstrEscapeFunction"],
        ];
        return [$headerAuslagen, $auslagen, $escapeFunctionsAuslagen];
    }
    
    public function renderKVView(){
        //Auslagenerstattungen
        $headerAuslagen = ["Projekt", "Auslage", "Organisation", "zuletzt geändert"];
        
        list($headerAuslagen, $auslagenWIP, $escapeFunctionsAuslagen) = $this->fetchAuslagenWithState("wip", "kv");
        $groupsAuslagen["Auslagenerstattungen KV fehlt"] = $auslagenWIP;
        list(/**/, $auslagenWIP,/**/) = $this->fetchAuslagenWithState("wip", "belege");
        $groupsAuslagen["Auslagenerstattungen Belege fehlen"] = $auslagenWIP;
        
        //TODO: Implementierung vom rest
        //$groups[] = ["name" => "Externe Projekte für StuRa Situng vorbereiten", "fields" => ["type" => "extern-express", "state" => "draft"]];
        
        $this->renderHeadline("Von den Kassenverantwortlichen zu erledigen");
        $this->renderTable($headerAuslagen, $groupsAuslagen, $escapeFunctionsAuslagen);
        
        $this->renderExportBankButton();
    }
    
    private function renderExportBankButton(){
        $auslagen = DBConnector::getInstance()->dbFetchAll(
            "auslagen",
            [DBConnector::FETCH_ASSOC],
            ["count" => ["id", DBConnector::GROUP_COUNT]],
            ["auslagen.state" => ["LIKE", "ok%"], "auslagen.payed" => ""],
            [],
            [],
            ["auslagen.id"]
        );
        
        ?>
        <form action="<?= URIBASE ?>menu/kv/exportBank">
            <button class="btn btn-primary" <?= end($auslagen)["count"] === 0 ? "disabled" : "" ?>>
                <i class="fa fa-fw fa-money"></i>&nbsp;Exportiere Überweisungen
            </button>
        </form>
        
        <?php
    }
    
    private function renderExportBank(){
        $header = ["Auslage", "Empfänger", "IBAN", "Verwendungszweck", "Auszuzahlen"];
        $auslagen = DBConnector::getInstance()->dbFetchAll(
            "auslagen",
            [DBConnector::FETCH_NUMERIC],
            [
                "projekte.id", "auslagen.id", "auslagen.name_suffix", // Auslagenlink
                "auslagen.zahlung-name",
                "auslagen.zahlung-iban",
                "projekte.id", "projekte.createdat", "auslagen.id", "auslagen.zahlung-vwzk", "auslagen.name_suffix", "projekte.name", //verwendungszweck
                "ausgaben" => ["beleg_posten.ausgaben", DBConnector::SUM_ROUND2],
                "einnahmen" => ["beleg_posten.einnahmen", DBConnector::SUM_ROUND2]
            ],
            ["auslagen.state" => ["LIKE", "ok%"], "auslagen.payed" => ""],
            [
                ["type" => "inner", "table" => "projekte", "on" => ["projekte.id", "auslagen.projekt_id"]],
                ["type" => "inner", "table" => "belege", "on" => ["belege.auslagen_id", "auslagen.id"]],
                ["type" => "inner", "table" => "beleg_posten", "on" => ["beleg_posten.beleg_id", "belege.id"]],
            ],
            [],
            ["auslagen.id"]
        );
        $obj = $this;
        $escapeFunctions = [
            [$this, "auslagenLinkEscapeFunction"],                      // 3 Parameter
            null,                                                       // 1 Parameter
            function($str){
                $p = $str;
                if (!$p) return '';
                $p = Crypto::decrypt_by_key_pw($p, Crypto::get_key_from_file(SYSBASE . '/secret.php'), URIBASE);
                $p = Crypto::unpad_string($p);
                return $p;
            },                                                       // 1 Parameter
            function($pId, $pCreate, $aId, $vwdzweck, $aName, $pName){  // 6 Parameter - Verwendungszweck
                $year = date("Y", strtotime($pCreate));
                $ret = "IP-$year-$pId-A$aId - $vwdzweck - $aName - $pName";
                if (strlen($ret) > 140){
                    $ret = substr($ret, 0, 140);
                }
                return $ret;
            },
            function($ausgaben, $einnahmen) use ($obj){                 // 2 Parameter
                return $obj->moneyEscapeFunction(floatval($ausgaben) - floatval($einnahmen));
            }
        ];
        if (count($auslagen) > 0){
            $this->renderTable($header, [$auslagen], $escapeFunctions);
        }else{
            $this->renderHeadline("Aktuell liegen keine Überweisungen vor.", 2);
        }
    }
    
    private function renderBooking(){
        
        list($hhps, $hhp_id) = $this->renderHHPSelector();
        
        $startDate = $hhps[$hhp_id]["von"];
        $endDate = $hhps[$hhp_id]["bis"];
    
        $bookedZahlungen = DBConnector::getInstance()->dbFetchAll("booking", [DBConnector::FETCH_ONLY_FIRST_COLUMN], ["zahlung_id"], ["canceled" => 0]);
        if (empty($bookedZahlungen)){
            //only remove nothing - if not set there would be an sql error
            $bookedZahlungen = [0];
        }
        if (!isset($endDate) || empty($endDate)){
            $alZahlung = DBConnector::getInstance()->dbFetchAll("konto", [DBConnector::FETCH_ASSOC], [], ["date" => [">=", $startDate], "id" => ["NOT IN", $bookedZahlungen]], [], ["value" => true]);
        }else{
            $alZahlung = DBConnector::getInstance()->dbFetchAll("konto", [DBConnector::FETCH_ASSOC], [], ["date" => ["BETWEEN", [$startDate, $endDate]], "id" => ["NOT IN", $bookedZahlungen]], [], ["value" => true]);
        }
    
        $this->renderKontoRefreshButton();
        
        $alGrund = DBConnector::getInstance()->dbFetchAll(
            "auslagen",
            [DBConnector::FETCH_ASSOC],
            [
                "auslagen.*",
                "projekte.name",
                "ausgaben" => ["beleg_posten.ausgaben", DBConnector::GROUP_SUM_ROUND2],
                "einnahmen" => ["beleg_posten.einnahmen", DBConnector::GROUP_SUM_ROUND2]
            ],
            ["auslagen.state" => ["LIKE", "instructed%"]],
            [
                ["type" => "inner", "table" => "projekte", "on" => ["projekte.id", "auslagen.projekt_id"]],
                ["type" => "inner", "table" => "belege", "on" => ["belege.auslagen_id", "auslagen.id"]],
                ["type" => "inner", "table" => "beleg_posten", "on" => ["beleg_posten.beleg_id", "belege.id"]],
            ],
            ["einnahmen" => true],
            ["auslagen.id"]
        );
        array_walk($alGrund, function(&$grund){
            $grund["value"] = floatval($grund["einnahmen"]) - floatval($grund["ausgaben"]);
        });
    
        //sort with reverse order
        usort($alGrund, function($e1, $e2){
            if ($e1["value"] === $e2["value"]){
                return 0;
            }else if ($e1["value"] > $e2["value"]){
                return 1;
            }else{
                return -1;
            }
        });
        ?>


        <a href="<?= URIBASE ?>menu/booking-history/" class="btn btn-primary"><i class="fa fa-fw fa-list "></i>
            Buchungsübersicht</a>
    
        <?php //var_dump($alZahlung[0]);
        ?>
        <table class="table table-striped">
            <thead>
            <tr>
                <th>Zahlungen</th>
                <th class="col-md-1">Beträge</th>
                <th>Belege</th>
            </tr>
            </thead>
            <?php
            $idxZahlung = 0;
            $idxGrund = 0;
            while ($idxZahlung < count($alZahlung) || $idxGrund < count($alGrund)){
    
                echo "<tr>";
                if (isset($alZahlung[$idxZahlung])){
                    if (isset($alGrund[$idxGrund])){
                        $value = min([floatval($alZahlung[$idxZahlung]["value"]), $alGrund[$idxGrund]["value"]]);
                    }else{
                        //var_dump($alZahlung[$idxZahlung]);
                        $value = floatval($alZahlung[$idxZahlung]["value"]);
                    }
                }else{
                    $value = $alGrund[$idxGrund]["value"];
                }
                echo "<td>";
    
                while (isset($alZahlung[$idxZahlung]) && floatval($alZahlung[$idxZahlung]["value"]) === $value){
                    echo "<input type='checkbox' class='booking__form-zahlung' data-value='{$value}' data-id='{$alZahlung[$idxZahlung]["id"]}'>";
                    $caption = "Z{$alZahlung[$idxZahlung]['id']} - ";
                    $title = "VALUTA: " . $alZahlung[$idxZahlung]["valuta"] . PHP_EOL .
                        "IBAN: " . $alZahlung[$idxZahlung]["empf_iban"] . PHP_EOL .
                        "BIC: " . $alZahlung[$idxZahlung]["empf_bic"];
                    //print_r($alZahlung[$idxZahlung]);
                    switch ($alZahlung[$idxZahlung]["type"]){
                        case "FOLGELASTSCHRIFT":
                            $caption .= "LASTSCHRIFT an ";
                            break;
                        case "ONLINE-UEBERWEISUNG":
                            $caption .= "ÜBERWEISUNG an ";
                            break;
                        case "GUTSCHRIFT":
                            $caption .= "GUTSCHRIFT von ";
                            break;
                        default: //Buchung, Entgeldabschluss,KARTENZAHLUNG...
                            $caption .= $alZahlung[$idxZahlung]["type"] . " an ";
                            break;
                    }
                    $caption .= $alZahlung[$idxZahlung]["empf_name"] . " - " . explode("DATUM", $alZahlung[$idxZahlung]["zweck"])[0];
                    $url = str_replace("//", "/", URIBASE . "/zahlung/" . $alZahlung[$idxZahlung]["id"]);
                    echo "<a href='" . htmlspecialchars($url) . "' title='" . htmlspecialchars($title) . "'>" . htmlspecialchars($caption) . "</a>";
                    $idxZahlung++;
                    echo "<br>";
                }
                echo "</td><td class='money'>";
                echo DBConnector::getInstance()->convertDBValueToUserValue($value, "money");
                echo "</td><td>";
                while (isset($alGrund[$idxGrund]) && $alGrund[$idxGrund]["value"] === $value){
                    echo "<input type='checkbox' class='booking__form-beleg' data-value='{$value}' data-id='{$alGrund[$idxGrund]['id']}' >";
    
                    $caption = "A" . $alGrund[$idxGrund]["id"] . " - " . $alGrund[$idxGrund]["name"] . " - " . $alGrund[$idxGrund]["name_suffix"];
                    $url = str_replace("//", "/", URIBASE . "/projekt/{$alGrund[$idxGrund]['projekt_id']}/auslagen/" . $alGrund[$idxGrund]["id"]);
                    echo "<a href=\"" . htmlspecialchars($url) . "\">" . $caption . "</a>";
                    $idxGrund++;
                    echo "<br>";
                }
                echo "</td>";
                echo "</tr>";
            }

            ?>
        </table>
        <form action="<?= URIBASE ?>menu/check-booking" method="GET" role="form" class="form-inline ajax">
            <div class="booking__panel-form col-xs-2">
                <h4>ausgewählte Zahlungen</h4>
                <div class="booking__zahlung">
                    <div id="booking__zahlung-not-selected">
                        <span><i>keine ID</i></span>
                        <span class="money">0.00</span>
                    </div>
                    <div class="booking__zahlung-sum text-bold">
                        <span>&Sigma;</span>
                        <span class="money">0.00</span>
                    </div>
                </div>
                <h4>ausgewählte Belege</h4>
                <div class="booking__belege">
                    <div id="booking__belege-not-selected">
                        <span><i>keine ID</i></span>
                        <span class="money">0.00</span>
                    </div>
                    <div class="booking__belege-sum text-bold">
                        <span>&Sigma;</span>
                        <span class="money">0.00</span>
                    </div>
                </div>
                <!--<div>
                    <label>Buchungstext</label>
                    <textarea name="booking-text" rows="3" class="form-control"></textarea>
                </div>-->
                <button id="booking__check-button" class="btn btn-primary">Buchung durchführen</button>
            </div>
        </form>
    
        <?php
    }
    
    private function renderHHPSelector(){
        $hhps = DBConnector::getInstance()->dbFetchAll("haushaltsplan", [DBConnector::FETCH_ASSOC, DBConnector::FETCH_UNIQUE_FIRST_COL_AS_KEY], [], [], [], ["von" => false]);
        if (!isset($hhps) || empty($hhps)){
            ErrorHandler::_errorExit("Konnte keine Haushaltspläne finden");
        }
        if (!isset($this->pathinfo["hhp-id"])){
            foreach (array_reverse($hhps, true) as $id => $hhp){
                if ($hhp["state"] === "final"){
                    $this->pathinfo["hhp-id"] = $id;
                }
            }
        }
        ?>
        <form action="<?= $this->pathinfo["hhp-id"] ?>">
            <div class="input-group col-xs-2 pull-right">
                <select class="selectpicker" id="hhp-id"><?php
                    foreach ($hhps as $id => $hhp){
                        $name = !empty($hhp["bis"]) ? $hhp["von"] . " bis " . $hhp["bis"] : "ab " . $hhp["von"];
                        ?>
                        <option value="<?= $id ?>" <?= $id == $this->pathinfo["hhp-id"] ? "selected" : "" ?>
                                data-subtext="<?= $hhp["state"] ?>"><?= $name ?>
                        </option>
                    <?php } ?>
                </select>
                <div class="input-group-btn">
                    <button type="submit" class="btn btn-primary load-hhp"><i class="fa fa-fw fa-refresh"></i>
                        Aktualisieren
                    </button>
                </div>
            </div>
        </form>
        <?php
        return [$hhps, $this->pathinfo["hhp-id"]];
    }
    
    private function renderKontoRefreshButton(){ ?>
        <form action="<?= URIBASE ?>rest/hibiscus" method="POST" role="form" class="form-inline ajax d-inline-block">
            <button type="submit" name="absenden" class="btn btn-primary">
                <i class="fa fa-fw fa-refresh"></i> neue Kontoauszüge abrufen
            </button>
            <input type="hidden" name="action" value="hibiscus">
            <input type="hidden" name="nonce" value="<?= $GLOBALS["nonce"] ?>">
        </form>
        <?php
    }
    
    private function renderBookingCheck(){
        if (!isset($_REQUEST["zahlung"])
            || !is_array($_REQUEST["zahlung"])
            || !isset($_REQUEST["beleg"])
            || !is_array($_REQUEST["beleg"])
        ){
            $errorMsg = "Bitte stelle sicher, das du alle Felder ausgefüllt hast.";
        }
        
        $zahlungen = $_REQUEST["zahlung"];
        $belege = $_REQUEST["beleg"];
        if ((count($zahlungen) > 1 && count($belege) > 1)
            || count($belege) < 1
            || count($zahlungen) < 1
            || count($zahlungen) > 1 // FIXME: should be allowed later
        ){
            $errorMsg = "Es kann immer nur 1 Zahlung zu n Belegen oder 1 Beleg zu n Zahlungen zugeordnet werden. Andere Zuordnungen sind nicht möglich!";
        }
        
        if (isset($errorMsg)){
            ErrorHandler::_errorExit($errorMsg);
        }else{
            //titel_id, kostenstelle, zahlung_id, beleg_id, user_id, comment, value
            $zahlungenDB = DBConnector::getInstance()->dbFetchAll("konto", [], ["id" => ["IN", $zahlungen]]);
            $belegeDB = DBConnector::getInstance()->dbFetchAll(
                "auslagen",
                [
                    "auslagen.projekt_id",
                    "auslagen_id" => "auslagen.id",
                    "belege_id" => "belege.id",
                    "titel_name",
                    "titel_nr",
                    "posten_id" => "beleg_posten.id",
                    "posten_short" => "beleg_posten.short",
                    "beleg_posten.einnahmen",
                    "beleg_posten.ausgaben",
                ],
                ["auslagen.id" => ["IN", $belege]],
                [
                    ["table" => "belege", "type" => "inner", "on" => ["belege.auslagen_id", "auslagen.id"]],
                    ["table" => "beleg_posten", "type" => "inner", "on" => ["beleg_posten.beleg_id", "belege.id"]],
                    ["table" => "projektposten", "type" => "inner", "on" =>
                        [
                            ["projektposten.id", "beleg_posten.projekt_posten_id"],
                            ["auslagen.projekt_id", "projektposten.projekt_id"]
                        ]
                    ],
                    ["table" => "haushaltstitel", "type" => "inner", "on" => ["projektposten.titel_id", "haushaltstitel.id"]],
                ]
            );
            $sum_zahlung = 0;
            $sum_beleg = 0;
            $res = [];
            foreach ($zahlungenDB as $zahlung){
                $sum_zahlung += $zahlung["value"];
                $rowZahlung = [
                    $zahlung["id"],
                    $zahlung["value"],
                ];
                foreach ($belegeDB as $beleg){
                    $sum_beleg += floatval($beleg["einnahmen"]);
                    $sum_beleg -= floatval($beleg["ausgaben"]);
                    $rowBeleg = [
                        $beleg["projekt_id"],
                        $beleg["auslagen_id"],
                        " ", // show no name in auslagenLinkEscapeFunction
                        $beleg["posten_short"],
                        $beleg["titel_nr"],
                        $beleg["titel_name"],
                    ];
                    if (floatval($beleg["einnahmen"]) != 0){
                        $rowBeleg[] = $beleg["einnahmen"];
                    }
                    if (floatval($beleg["ausgaben"]) != 0){
                        $rowBeleg[] = -$beleg["ausgaben"];
                    }
                    $rowBeleg[] = $beleg["posten_id"];
    
                    $res[] = array_merge($rowZahlung, $rowBeleg);
                }
            }
            if (abs($sum_zahlung - $sum_beleg) >= 0.01){
                ErrorHandler::_errorExit("Summe Zahlung ($sum_zahlung) und Summe Beleg ($sum_beleg) passen nicht zusammen!");
            }
            $header = [
                "Zahlung", "Zahlung-Betrag", "Auslage", "Beleg-Posten", "Titel Nr", "Titel", "Posten-Betrag", "Buchungstext",
            ];
            $this->renderHeadline("Buchung bestätigen");
            ?>
            <form method="POST" action="./save-booking">
                <?php
                //var_dump($res);
                $obj = $this;
                $this->renderTable($header, [$res], [
                    function($zahlung_id) use ($obj){
                        return $obj->defaultEscapeFunction($zahlung_id);
                    },
                    [$this, "moneyEscapeFunction"],
                    [$this, "auslagenLinkEscapeFunction"],
                    null,
                    function($titelnr){
                        return str_replace(" ", "&nbsp;", trim($titelnr));
                    },
                    null,
                    [$this, "moneyEscapeFunction"],
                    function($posten_id){
                        return "<textarea name='text[$posten_id]' class='form-control'></textarea>";
                    }
                ]);
                foreach ($zahlungen as $zahlung){
                    $this->renderHiddenInput("zahlung[]", $zahlung);
                }
                foreach ($belege as $beleg){
                    $this->renderHiddenInput("beleg[]", $beleg);
                }
                ?>
                <button class="btn btn-primary pull-right">Buchung durchführen</button>
            </form>
            <?php
        }
        
    }
    
    public function renderKonto(){
        
        list($hhps, $selected_id) = $this->renderHHPSelector();
        $startDate = $hhps[$selected_id]["von"];
        $endDate = $hhps[$selected_id]["bis"];
        if (is_null($endDate) || empty($endDate)){
            $alZahlung = DBConnector::getInstance()->dbFetchAll(
                "konto",
                [DBConnector::FETCH_ASSOC],
                [],
                ["valuta" => [">", $startDate]],
                [],
                ["id" => false]
            );
        }else{
            $alZahlung = DBConnector::getInstance()->dbFetchAll(
                "konto",
                [DBConnector::FETCH_ASSOC],
                [],
                ["valuta" => ["BETWEEN", [$startDate, $endDate]]],
                [],
                ["id" => false]
            );
        }
        $this->renderKontoRefreshButton();
        
        ?>


        <table class="table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Datum</th>
                <th>Empfänger</th>
                <th class="visible-md visible-lg">Verwendungszweck</th>
                <th class="visible-md visible-lg">IBAN</th>
                <th class="money">Betrag</th>
                <th class="money">Saldo</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($alZahlung as $zahlung){ ?>
                <tr title="<?= htmlspecialchars($zahlung["type"] . " - IBAN: " . $zahlung["empf_iban"] . " - BIC: " . $zahlung["empf_bic"]
                    . PHP_EOL . $zahlung["zweck"]) ?>">
                    <td><?= htmlspecialchars($zahlung["id"]) ?></td>
                    <td><?= htmlspecialchars($zahlung["valuta"]) ?></td>
                    <td><?= htmlspecialchars($zahlung["empf_name"]) ?></td>
                    <td class="visible-md visible-lg"><?= htmlspecialchars(explode("DATUM", $zahlung["zweck"])[0]) ?></td>
                    <td class="visible-md visible-lg"><?= htmlspecialchars($zahlung["empf_iban"]) ?></td>
                    <td class="money"><?= DBConnector::getInstance()->convertDBValueToUserValue($zahlung["value"], "money") ?></td>
                    <td class="money"><?= DBConnector::getInstance()->convertDBValueToUserValue($zahlung["saldo"], "money") ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php
    }
    
    private function saveBooking(){
    
        $zahlungen = $_REQUEST["zahlung"];
        $belege = $_REQUEST["beleg"];
        $text = $_REQUEST["text"];
        
        $maxBookingId = DBConnector::getInstance()->dbFetchAll("booking", ["id" => ["id", DBConnector::MAX]]);
        
        if (is_array($maxBookingId) && !empty($maxBookingId)){
            $maxBookingId = intval($maxBookingId[0]["id"]);
        }else{
            $maxBookingId = 1;
        }
        //check if allready booked
        $bookingDBbelege = DBConnector::getInstance()->dbFetchAll(
            "booking",
            ["zahlung_id", "belegposten_id"],
            ["canceled" => 0, "belegposten_id" => ["IN", $belege],]
        );
        $bookingDBzahlung = DBConnector::getInstance()->dbFetchAll(
            "booking",
            ["zahlung_id", "belegposten_id"],
            ["canceled" => 0, "zahlung_id" => ["IN", $zahlungen],]
        );
    
        if (count($bookingDBbelege) + count($bookingDBzahlung) > 0){
            ErrorHandler::_renderErrorPage(["msg" => "Beleg oder Zahlung bereits verknüpft - " . print_r(array_merge($bookingDBzahlung, $bookingDBbelege), true), "code" => "500 Interner Fehler"]);
        }
        
        $zahlungenDB = DBConnector::getInstance()->dbFetchAll("konto", ["id", "value"], ["id" => ["IN", $zahlungen]]);
        $belegeDB = DBConnector::getInstance()->dbFetchAll(
            "auslagen",
            [
                "titel_id",
                "posten_id" => "beleg_posten.id",
                "beleg_posten.einnahmen",
                "beleg_posten.ausgaben",
            ],
            ["auslagen.id" => ["IN", $belege]],
            [
                ["table" => "belege", "type" => "inner", "on" => ["belege.auslagen_id", "auslagen.id"]],
                ["table" => "beleg_posten", "type" => "inner", "on" => ["beleg_posten.beleg_id", "belege.id"]],
                ["table" => "projektposten", "type" => "inner", "on" =>
                    [
                        ["projektposten.id", "beleg_posten.projekt_posten_id"],
                        ["auslagen.projekt_id", "projektposten.projekt_id"]
                    ]
                ],
                ["table" => "haushaltstitel", "type" => "inner", "on" => ["projektposten.titel_id", "haushaltstitel.id"]],
            ]
        );
        
        $zahlung_sum = 0;
        foreach ($zahlungenDB as $zahlung){
            DBConnector::getInstance()->dbBegin();
            $zahlung_sum += $zahlung["value"];
            $belege_sum = 0;
            foreach ($belegeDB as $beleg){
                $value = 0;
                if (floatval($beleg["einnahmen"]) != 0){
                    $value = $beleg["einnahmen"];
                }
                if (floatval($beleg["ausgaben"]) != 0){
                    $value = -$beleg["ausgaben"];
                }
                $belege_sum += $value;
                DBConnector::getInstance()->dbInsert("booking", [
                    "id" => ++$maxBookingId,
                    "titel_id" => $beleg["titel_id"],
                    "zahlung_id" => $zahlung["id"],
                    "belegposten_id" => $beleg["posten_id"],
                    "user_id" => DBConnector::getInstance()->getUser()["id"],
                    "comment" => $text[$beleg["posten_id"]],
                    "value" => $value,
                    "kostenstelle" => 0,
                ]);
            }
        }
        
        //check if user input was correct
        if (abs($zahlung_sum - $belege_sum) >= 0.01){
            DBConnector::getInstance()->dbRollBack();
            ErrorHandler::_errorExit("Falsche Daten wurden übvertragen: $zahlung_sum != $belege_sum");
        }else{
            DBConnector::getInstance()->dbCommit();
            $this->renderBookingHistory();
        }
        
    }
    
    public function renderBookingHistory(){
        list($hhps, $hhp_id) = $this->renderHHPSelector();
        
        $ret = DBConnector::getInstance()->dbFetchAll("booking",
            [DBConnector::FETCH_ASSOC],
            ["booking.id", "titel_nr", "zahlung_id", "booking.value", "canceled", "beleg_posten.short", "auslagen_id", "projekt_id", "timestamp", "username", "fullname", "kostenstelle", "comment"],
            ["hhp_id" => $hhp_id],
            [
                ["type" => "left", "table" => "user", "on" => ["booking.user_id", "user.id"]],
                ["type" => "left", "table" => "haushaltstitel", "on" => ["booking.titel_id", "haushaltstitel.id"]],
                ["type" => "left", "table" => "haushaltsgruppen", "on" => ["haushaltsgruppen.id", "haushaltstitel.hhpgruppen_id"]],
                ["type" => "left", "table" => "beleg_posten", "on" => ["booking.belegposten_id", "beleg_posten.id"]],
                ["type" => "inner", "table" => "belege", "on" => ["belege.id", "beleg_posten.beleg_id"]],
                ["type" => "inner", "table" => "auslagen", "on" => ["belege.auslagen_id", "auslagen.id"]],
            ],
            ["timestamp" => true, "id" => true]
        );
        if (!empty($ret)){
    
            //var_dump(reset($ret));
            $this->renderHeadline("Buchungshistorie");
            ?>
            <table class="table" align="right">
                <thead>
                <tr>
                    <th>B-Nr</th>
                    <th class="col - xs - 1">Betrag (EUR)</th>
                    <th class="col - xs - 1">Titel</th>
                    <th>Beleg</th>
                    <th>Buchungs-Datum</th>
                    <th>Zahlung</th>
                    <th>Stornieren</th>
                    <th>Kommentar</th>
                </tr>
                </thead>
                <tbody>
                <?php
                foreach ($ret as $lfdNr => $row){
                    $userStr = isset($row["fullname"]) ? $row["fullname"] . " (" . $row["username"] . ")" : $row["username"];
                    $projektId = $row["projekt_id"];
                    $auslagenId = $row["auslagen_id"]
                    ?>
                    <tr class=" <?= $row["canceled"] != 0 ? "booking__canceled-row" : "" ?>">

                        <td><a class="link-anchor" name="<?= $row["id"] ?>"></a><?= $row["id"]/*$lfdNr + 1*/ ?></td>

                        <td class="money <?= $row['value'] < 0 ? TextStyle::DANGER_DARK : TextStyle::GREEN ?> <?= TextStyle::BOLD ?>"><?= DBConnector::getInstance()->convertDBValueToUserValue($row['value'], "money") ?></td>

                        <td class="<?= TextStyle::PRIMARY . " " . TextStyle::BOLD ?>"><?= str_replace(" ", "&nbsp;", trim(htmlspecialchars($row['titel_nr']))) ?></td>

                        <td><?= generateLinkFromID("A$auslagenId&nbsp;-&nbsp;" . $row['short'], "projekt/$projektId/auslagen/$auslagenId", TextStyle::BLACK) ?></td>

                        <td value="<?= $row['timestamp'] ?>">
                            <?= date("d.m.Y", strtotime($row['timestamp'])) ?>&nbsp;<!--
                        --><i title="<?= $row['timestamp'] . " von " . $userStr ?>"
                              class="fa fa-fw fa-question-circle" aria-hidden="true"></i>
                        </td>

                        <td><?= generateLinkFromID($row['zahlung_id'], "", TextStyle::BLACK) ?></td>
                        <?php if ($row["canceled"] == 0){ ?>
                            <td>
                                <form id="cancel" role="form" action="<?= $_SERVER["PHP_SELF"]; ?>" method="POST"
                                      enctype="multipart/form-data" class="ajax">
                                    <input type="hidden" name="action" value="booking.history.cancel"/>
                                    <input type="hidden" name="nonce" value="<?= $GLOBALS['nonce']; ?>"/>
                                    <input type="hidden" name="booking.id" value="<?= $row["id"]; ?>"/>
                                    <input type="hidden" name="hhp.id" value="<?= $hhp_id; ?>"/>

                                    <a href="javascript:void(false);" class='submit-form <?= TextStyle::DANGER ?>'>
                                        <i class='fa fa-fw fa-ban'></i>&nbsp;Stornieren
                                    </a>
                                </form>
                            </td>
                        <?php }else{ ?>
                            <td>Durch <a href='#<?= $row['canceled'] ?>'>B-Nr: <?= $row['canceled'] ?></a></td>
                        <?php } ?>
                        <td class="col-xs-4 <?= TextStyle::SECONDARY ?>"><?= htmlspecialchars($row['comment']) ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
            <?php
        }else{
            $this->renderHeadline("bisher keine Buchungen in diesem HH-Jahr vorhanden.", 2);
        }
    }
}
