<?php
/**
 * Created by PhpStorm.
 * User: lukas
 * Date: 03.02.18
 * Time: 14:43
 */

class MenuRenderer implements Renderer{
    const DEFAULT = "mygremium";
    
    
    public function render($args = []){
        if (empty($args) || !isset($args[0]))
            $args = [self::DEFAULT];
        $attributes = AuthHandler::getInstance()->getAttributes();
        switch ($args[0]){
            case "mygremium":
            case "allgremium":
                if ($args[0] === "allgremium")
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
                HTMLPageRenderer::registerProfilingBreakpoint("start-rendering");
                MenuRenderer::renderProjekte($gremien);
                break;
            
            case "mykonto":
                MenuRenderer::renderMyProfile();
                break;
            case "stura":
                $groups[] = ["name" => "Externe Anträge", "fields" => ["type" => "extern-express", "state" => "need-stura",]];
                $groups[] = ["name" => "Interne Projekte", "fields" => ["type" => "projekt-intern", "state" => "need-stura",]];
                $groups[] = ["name" => "Beschlossen von Haushahaltsverantwortlichen, zur Verkündung",
                    "fields" => ["type" => "projekt-intern", "state" => "ok-by-hv",]];
                
                $mapping[] = ["p-name" => "projekt.name", "org-name" => "projekt.org.name"];
                $mapping[] = ["p-name" => "projekt.name", "org-name" => "projekt.org.name"];
                $mapping[] = ["p-name" => "projekt.name", "org-name" => "projekt.org.name"];
                MenuRenderer::renderTable($groups, $mapping);
                break;
            case "hv":
                $groups[] = ["name" => "zu Bearbeitende Interne Projekte", "fields" => ["type" => "projekt-intern", "state" => "wip",]];
                $groups[] = ["name" => "Externe Projekte für StuRa Situng vorbereiten", "fields" => ["type" => "extern-express", "state" => "draft"]];
                $groups[] = ["name" => "Auslagenerstattungen nur noch HV", "fields" => ["type" => "auslagenerstattung", "state" => "ok-by-kv",]];
                $groups[] = ["name" => "Auslagenerstattungen", "fields" => ["type" => "auslagenerstattung", "state" => "wip",]];
                
                $mapping[] = ["p-name" => "projekt.name", "org-name" => "projekt.org.name"];
                $mapping[] = ["p-name" => "projekt.name", "org-name" => "projekt.org.name"];
                $mapping[] = ["p-name" => "projekt.name", "org-name" => "projekt.org.name"];
                $mapping[] = ["p-name" => "projekt.name", "org-name" => "projekt.org.name"];
                
                MenuRenderer::renderTable($groups, $mapping);
                
                break;
            case "kv":
                $groups[] = ["name" => "Noch zu tätigende Zahlungen", "fields" => ["type" => "zahlung-anweisung", "state" => "ok",]];
                $groups[] = ["name" => "Auslagenerstattungen nur noch KV", "fields" => ["type" => "auslagenerstattung", "state" => "ok-by-hv",]];
                $groups[] = ["name" => "Auslagenerstattungen", "fields" => ["type" => "auslagenerstattung", "state" => "wip",]];
                
                $mapping[] = ["p-name" => "projekt.name", "org-name" => "projekt.org"];
                $mapping[] = ["p-name" => "projekt.name", "org-name" => "projekt.org.name"];
                $mapping[] = ["p-name" => "projekt.name", "org-name" => "projekt.org.name"];
                MenuRenderer::renderTable($groups, $mapping);
                break;
            case "hhp":
                $antrag_id = null;
                if (isset($_REQUEST["id"])){
                    $antrag_id = $_REQUEST["id"];
                }
                HTMLPageRenderer::registerProfilingBreakpoint("renderhhp-start");
                MenuRenderer::renderHaushaltsplan($antrag_id);
                break;
            case "booking":
                require "../template/booking.tpl";
                //TODO: FIXME!;
                break;
            case "konto":
                global $HIBISCUSGROUP;
                AuthHandler::getInstance()->requireGroup($HIBISCUSGROUP);
                $selected_hhp_id = null;
                if (isset($_REQUEST["id"])){
                    $selected_hhp_id = $_REQUEST["id"];
                }
                MenuRenderer::renderKonto($selected_hhp_id);
                break;
            case "booking-history":
                $selected_hhp_id = null;
                if (isset($_REQUEST["id"])){
                    $selected_hhp_id = $_REQUEST["id"];
                }
                MenuRenderer::renderBookingHistory($selected_hhp_id);
                //TODO FIXME;
                break;
            default:
                die("?!? could not interpret '$args[0]' as menu name :( ");
        }
    }
    
    public function renderProjekte($gremien){
        //$enwuerfe = DBConnector::getInstance()->dbFetchAll("antrag",["state" => "draft","creator" => AuthHandler::getInstance()->getUserName()]);
        //$projekte = DBConnector::getInstance()->getProjectFromGremium($gremien, "projekt-intern");
        $projekte = DBConnector::getInstance()->dbFetchAll("projekte", ["org", "projekte.*"], ["org" => ["in", $gremien]], [], ["org" => true], true);
        /*if (AuthHandler::getInstance()->hasGroup("ref-finanzen")){
            $extVereine = ["Bergfest.*", ".*KuKo.*", ".*ILSC.*", "Market Team.*", ".*Second Unit Jazz.*", "hsf.*", "hfc.*", "FuLM.*", "KSG.*", "ISWI.*"]; //TODO: From external source
            $ret = DBConnector::getInstance()->getProjectFromGremium($extVereine, "extern-express");
            if ($ret !== false){
                //var_dump($ret);
                $projekte = array_merge($projekte, $ret);
            }
        }*/
        //var_dump($projekte);
        ?>
        <div class="panel-group" id="accordion">
            <?php $i = 0;
            if (isset($projekte)){
                foreach ($projekte as $gremium => $inhalt){
                    if (count($inhalt) == 0) continue; ?>
                    <div class="panel panel-default">
                        <div class="panel-heading collapsed" data-toggle="collapse" data-parent="#accordion"
                             href="#collapse<?php echo $i; ?>">

                            <h4 class="panel-title">
                                <i class="fa fa-fw fa-togglebox"></i> <?= $gremium ?>
                            </h4>
                        </div>
                        <div id="collapse<?php echo $i; ?>" class="panel-collapse collapse">
                            <div class="panel-body">
                                <?php $j = 0; ?>
                                <div class="panel-group" id="accordion<?php echo $i; ?>">
                                    <?php foreach ($inhalt as $projekt){
                                        $id = $projekt["id"];
                                        $projekt["_ref"] = []; //FIXME
                                        ?>
                                        <div class="panel panel-default">
                                            <div class="panel-link"><?= generateLinkFromID($id, "projekt/" . $id) ?>
                                            </div>
                                            <div class="panel-heading collapsed <?= count($projekt["_ref"]) === 0 ? "empty" : "" ?>"
                                                 data-toggle="collapse" data-parent="#accordion<?php echo $i ?>"
                                                 href="#collapse<?php echo $i . "-" . $j; ?>">
                                                <h4 class="panel-title">
                                                    <i class="fa fa-togglebox"></i><span
                                                            class="panel-projekt-name"><?= $projekt["name"] ?></span>
                                                    <span class="label label-info project-state-label"><?= ProjektHandler::getStateString($projekt["state"]) ?></span>
                                                </h4>
                                            </div>
                                            <?php if (count($projekt["_ref"]) !== 0){ ?>
                                                <div id="collapse<?php echo $i . "-" . $j; ?>"
                                                     class="panel-collapse collapse">
                                                    <div class="panel-body">


                                                        <table class="table">
                                                            <thead>
                                                            <th>ID</th>
                                                            <th>Type</th>
                                                            <th>Zahlungsempfänger</th>
                                                            <th>Betrag</th>
                                                            <th>Status</th>
                                                            </thead>
                                                            <tbody>
                                                            <?php foreach ($projekt["_ref"] as $a_id => $a_inhalt){ ?>
                                                                <tr>
                                                                    <td><?= generateLinkFromID($a_id, $a_inhalt["token"]) ?></td>
                                                                    <td><?= $a_inhalt["type"] ?></td>
                                                                    <td><?= isset($a_inhalt["_inhalt"]["antragsteller.name"]) ? $a_inhalt["_inhalt"]["antragsteller.name"] : "" ?></td>
                                                                    <td>FIXME</td>
                                                                    <td>
                                                                        <span class="label label-info"><?php echo getStateString($a_inhalt["type"], $a_inhalt["revision"], $a_inhalt["state"]); ?></span>
                                                                    </td>
                                                                </tr>
                                                            <?php } ?>
                                                            </tbody>
                                                        </table>

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
            }
            ?>
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
    
    $form = [
        "layout" => [
            ["id" => "myiban",
                "type" => "iban",
                "title" => "meine IBAN",
                "value" => $iban === "" ? $iban : "",
                "placeholder" => "DE ...",
                "width" => 12,
                "opts" => ["required"],
            ],
        ],
    ];
    ?>

    <form id="editantrag" role="form" action="<?= $_SERVER["PHP_SELF"]; ?>" method="POST"
          enctype="multipart/form-data" class="ajax">
        <input type="hidden" name="action" value="mykonto.update"/>
        <input type="hidden" name="nonce" value="<?= $GLOBALS["nonce"]; ?>"/>
        <?php renderForm($form); ?>
        <a href="javascript:void(false);" class='btn btn-success submit-form validate pull-right' data-name="iban"
           data-value="">Speichern</a>
    </form>
    
    <?php
    
}
    
    public function renderTable($groups, $mapping){
        $res = [];
        $header = ["ID", "Name", "Organisation", "Summe", "Status", "letzte Änderung"];
        
        if (!isset($groups)) return "groups leer";
        if (!isset($mapping)) return "Mapping leer";
        if (count($mapping) !== count($groups)) return "Mapping stimmt nicht mit Groups überein (Anzahl)";
        $name2Nr = [];
        foreach ($groups as $nr => $data){
            $name2Nr[$data["name"]] = $nr;
            $fields = $data["fields"];
            $res[$data["name"]] = DBConnector::getInstance()->dbFetchAll("antrag", [], $fields, [], [], true, true);
            $ids = array_keys($res[$data["name"]]);
            foreach ($ids as $id){
                $res[$data["name"]][$id]["_inhalt"] = betterValues(DBConnector::getInstance()->dbFetchAll("inhalt", [], ["antrag_id" => $id]));
                //var_dump($res[$data["name"]]);
            }
            //var_dump($res);
        }
        //var_dump($name2Nr);
        //var_dump($mapping);
        ?>
            <table class="table">
                <thead>
                <tr>
                    <?php
                    foreach ($header as $titel){
                        echo "<th>$titel</th>";
                    }
                    ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($res as $name => $inhalt){ ?>
                    <tr>
                        <th id="par" colspan="6"><?php echo $name; ?></th>
                    </tr>
                    <?php foreach ($inhalt as $id => $row){ ?>
                        <tr>
                            <td><?php echo $id; ?></td>
                            <td><?php echo generateLinkFromID($row["_inhalt"][$mapping[$name2Nr[$name]]["p-name"]], $row["token"]); ?></td>
                            <td><?php echo $row["_inhalt"][$mapping[$name2Nr[$name]]["org-name"]]; ?></td>
                            <td>Beantragte Summe</td>
                            <td>
                                <div class="label label-primary"><?php echo getStateString($row["type"], $row["revision"], $row["state"]); ?></div>
                            </td>
                            <td><?php echo $row["lastupdated"]; ?></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
                </tbody>
            </table>
        <?php
    }
    
    /**
     * @param int $selected_id default: oldest HHP with state final
     *
     * @return bool|void false if error else void
     */
    public function renderHaushaltsplan($selected_id = null){
            list($hhps, $selected_id) = MenuRenderer::renderHHPSelector("hhp", $selected_id);
            $editable = ($hhps[$selected_id]["state"] !== "final");
            ?>
            <button class="btn btn-danger">Diesen HHP löschen</button>
            <button class="btn btn-primary">neuen HHP anlegen</button>
            <button class="btn btn-warning">Statuswechsel</button>
            <?php
            $hhp = $hhps[$selected_id];
            $groups = DBConnector::getInstance()->dbgetHHP($selected_id);
            //var_dump($groups);
            ?>
            <h1>
                Haushaltsplan <?= $hhp["revision"] . " (" . getStateString($hhp["type"], $hhp["revision"], $hhp["state"]) . ")" ?></h1>
            <table class="table table-striped">
                <?php
                $group_nr = 1;
                $type = 0;
                foreach ($groups as $group){
                    if (count($group) === 0) continue;
                    if ($type !== array_values($group)[0]["type"])
                        $group_nr = 1;
                    
                    $type = array_values($group)[0]["type"];
                    ?>
                    <thead>
                    <tr>
                        <th class="bg-info"
                            colspan="42"><?= ($type + 1) . "." . $group_nr++ . " " . array_values($group)[0]["gruppen_name"] ?></th>
                    </tr>
                    <tr>
                        <th></th>
                        <th>Titelnr</th>
                        <th>Titelname</th>
                        <th class="money"><?= "soll-" . (array_values($group)[0]["type"] == 0 ? "Einnahmen" : "Ausgaben") ?></th>
                        <th class="money"><?= "ist-" . (array_values($group)[0]["type"] == 0 ? "Einnahmen" : "Ausgaben") . " (gebucht)" ?></th>
                        <th class="money"><?= "ist-" . (array_values($group)[0]["type"] == 0 ? "Einnahmen" : "Ausgaben") . " (beschlossen)" ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $gsum_soll = 0;
                    $gsum_ist = 0;
                    foreach ($group as $row){
                        if (!isset($row["_booked"]))
                            $row["_booked"] = 0;
                        $gsum_soll += $row["value"];
                        $gsum_ist += $row["_booked"];
                        ?>
                        <tr>
                            <td></td>
                            <td><?= $row["titel_nr"] ?></td>
                            <td><?= $row["titel_name"] ?></td>
                            <td class="money"><?= convertDBValueToUserValue($row["value"], "money") ?></td>
                            <td class="money <?= MenuRenderer::checkTitelBudget($row["value"], $row["_booked"]) ?>">
                                <?= convertDBValueToUserValue($row["_booked"], "money") ?>
                            </td>
                        </tr>
                        
                        
                        <?php
                    } ?>
                    <tr class="table-sum-footer">
                        <td colspan="3"></td>
                        <td class="money table-sum-hhpgroup"><?= convertDBValueToUserValue($gsum_soll, "money") ?></td>
                        <td class="money table-sum-hhpgroup"><?= convertDBValueToUserValue($gsum_ist, "money") ?></td>
                    </tr>
                    </tbody>
                    
                    <?php
                } ?>

            </table>
        <?php
        return;
    }
    
    private function renderHHPSelector($tabname, $selected_id){
        $hhps = DBConnector::getInstance()->dbFetchAll("antrag", [], ["type" => "haushaltsplan"], [], ["lastupdated" => 0], true, true);
        if (!isset($selected_id)){
            foreach (array_reverse($hhps, true) as $id => $hhp){
                if ($hhp["state"] === "final"){
                    $selected_id = $id;
                }
            }
        } ?>
        <form>
            <div class="input-group col-xs-2 pull-right">
                <!--<input type="number" class="form-control" name="year" value=<?= date("Y") ?>>-->
                <input type="hidden" name="tab" value="<?= $tabname ?>">
                <select class="selectpicker" name="id"><?php
                    foreach ($hhps as $id => $hhp){
                        ?>
                        <option value="<?= $id ?>" <?= $id == $selected_id ? "selected" : "" ?>
                                data-subtext="<?= getStateString($hhp["type"], $hhp["revision"], $hhp["state"]) ?>"><?= $hhp["revision"] ?>
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
        
        <?php if (array_search($selected_id, array_keys($hhps)) === false){
            var_dump($selected_id);
            var_dump($hhps);
            die("Konnte zugehörigen HHP nicht finden. :(");
            return false;
        }
        return [$hhps, $selected_id];
    }
    
    /**
     * @param $should
     * @param $is
     *
     * @return string
     */
    private function checkTitelBudget($should, $is){
        if ($is > $should){
            if ($is > $should * 1.5){
                return "hhp-danger";
            }else{
                return "hhp-warning";
            }
        }else{
            return "";
        }
    }

public function renderKonto($selected_id){
    global $nonce, $URIBASE;
    ?>
    <div class="col-md-11 col-xs-12 container main">
    <?php
    $hhps = DBConnector::getInstance()->dbFetchAll("antrag", [], ["type" => "haushaltsplan"], [], ["lastupdated" => 0], true, true);
    if (!isset($selected_id)){
        foreach (array_reverse($hhps, true) as $id => $hhp){
            if ($hhp["state"] === "final"){
                $selected_id = $id;
            }
        }
    }
    
    $year = $hhps[$selected_id]["revision"];
    $startDate = "$year-01-01";
    $endDate = "$year-12-31";
    $alZahlung = DBConnector::getInstance()->dbFetchAll("konto", [], ["date" => ["BETWEEN", [$startDate, $endDate]]], [], ["id" => false]);
    
    ?>
    <form>
        <div class="input-group col-xs-2 pull-right">
            <!--<input type="number" class="form-control" name="year" value=<?= date("Y") ?>>-->
            <input type="hidden" name="tab" value="konto">
            <select class="selectpicker" name="id"><?php
                foreach ($hhps as $id => $hhp){
                    ?>
                    <option value="<?= $id ?>" <?= $id == $selected_id ? "selected" : "" ?>
                            data-subtext="<?= getStateString($hhp["type"], $hhp["revision"], $hhp["state"]) ?>"><?= $hhp["revision"] ?>
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
    <form action="<?php echo $URIBASE; ?>" method="POST" role="form" class="form-inline ajax d-inline-block">
        <button type="submit" name="absenden" class="btn btn-primary"><i class="fa fa-fw fa-refresh"></i> neue
            Kontoauszüge
            abrufen
        </button>
        <input type="hidden" name="action" value="hibiscus">
        <input type="hidden" name="nonce" value="<?php echo $nonce; ?>">
    </form>
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
                <td class="visible-md visible-lg"><?= htmlspecialchars($zahlung["zweck"]) ?></td>
                <td class="visible-md visible-lg"><?= htmlspecialchars($zahlung["empf_iban"]) ?></td>
                <td class="money"><?= convertDBValueToUserValue($zahlung["value"], "money") ?></td>
                <td class="money"><?= convertDBValueToUserValue($zahlung["saldo"], "money") ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php
}
    
    public function renderBookingHistory($selected_hhp_id = null){
        global $nonce;
        MenuRenderer::renderHHPSelector("booking.history", $selected_hhp_id);
        
        $ret = DBConnector::getInstance()->dbFetchAll("booking",
            ["booking.id", "titel_nr", "zahlung_id", "booking.value", "canceled", "beleg_id", "timestamp", "username", "fullname", "kostenstelle", "comment"],
            ["hhp_id" => $selected_hhp_id],
            [
                ["type" => "left", "table" => "user", "on" => ["booking.user_id", "user.id"]],
                ["type" => "left", "table" => "haushaltstitel", "on" => ["booking.titel_id", "haushaltstitel.id"]],
                ["type" => "left", "table" => "haushaltsgruppen", "on" => ["haushaltsgruppen.id", "haushaltstitel.hhpgruppen_id"]]
            ],
            ["timestamp" => true, "id" => true]
        );
        
        //var_dump(reset($ret));
        ?>
        <table class="table" align="right">
            <thead>
            <tr>
                <th>B-Nr</th>
                <th class="col-xs-1">Betrag (EUR)</th>
                <th class="col-xs-1">Titel</th>
                <th>Beleg</th>
                <th>Datum</th>
                <th>Zahlung</th>
                <th>Stornieren</th>
                <th>Kommentar</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($ret as $lfdNr => $row){
                $userStr = isset($row["fullname"]) ? $row["fullname"] . " (" . $row["username"] . ")" : $row["username"];
                ?>
                <tr class="<?= $row["canceled"] != 0 ? "booking__canceled-row" : "" ?>">

                    <td><a class="link-anchor" name="<?= $row["id"] ?>"></a><?= $row["id"]/*$lfdNr + 1*/ ?></td>

                    <td class="money <?= $row['value'] < 0 ? TextStyle::DANGER_DARK : TextStyle::GREEN ?> <?= TextStyle::BOLD ?>"><?= convertDBValueToUserValue($row['value'], "money") ?></td>

                    <td class="<?= TextStyle::PRIMARY . " " . TextStyle::BOLD ?>"><?= htmlspecialchars($row['titel_nr']) ?></td>

                    <td><?= generateLinkFromID($row['beleg_id'], "", TextStyle::BLACK) ?></td>

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
                                <input type="hidden" name="nonce" value="<?= $nonce; ?>"/>
                                <input type="hidden" name="booking.id" value="<?= $row["id"]; ?>"/>
                                <input type="hidden" name="hhp.id" value="<?= $selected_hhp_id; ?>"/>

                                <a href="javascript:void(false);" class='submit-form <?= TextStyle::DANGER ?>'>
                                    <i class='fa fa-fw fa-ban'></i>&nbsp;Stornieren
                                </a>
                            </form>
                        </td>
                    <?php }else{
                        ?>
                        <td>Durch <a href='#<?= $row['canceled'] ?>'>B-Nr: <?= $row['canceled'] ?></a></td>
                    <?php } ?>
                    <td class="col-xs-4 <?= TextStyle::SECONDARY ?>"><?= htmlspecialchars($row['comment']) ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php
    }
}