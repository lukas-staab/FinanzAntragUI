<?php
/**
 * Created by PhpStorm.
 * User: konsul
 * Date: 26.02.19
 * Time: 19:36
 */

class BookingHandler
	extends Renderer{
	
	private $routeInfo;
	
	public function __construct($routeInfo){
		$this->routeInfo = $routeInfo;
		
	}
	
	public function render(){
		
		switch ($this->routeInfo["action"]){
			case "instruct":
				$this->renderBooking("instruct");
			break;
			case "confirm-instruct":
				$this->setBookingTabs("text", $this->routeInfo["hhp-id"]);
				$this->renderBookingText();
			break;
			case "kasse":
			case "bank":
			case "sparbuch":
				(AUTH_HANDLER)::getInstance()->requireGroup(HIBISCUSGROUP);
				$this->renderKonto($this->routeInfo["action"]);
			break;
			case "history":
				$this->renderBookingHistory("history");
			break;
			case "export-csv":
				$this->renderCSV();
			break;
			case "export-zip":
				$this->renderFullBookingZip();
			break;
			default:
				ErrorHandler::_errorExit("Action: {$this->routeInfo['action']} kann nicht interpretiert werden");
			break;
		}
	}
	
	private function setBookingTabs($active, $active_hhp_id){
		$linkbase = URIBASE . "booking/$active_hhp_id/";
		$tabs = [
			"instruct" => "<i class='fa fa-fw fa-legal'></i> Anweisen",
			"text" => "<i class='fa fa-fw fa-file-text-o'></i> Durchführen",
			"history" => "<i class='fa fa-fw fa-history'></i> Historie",
		];
		HTMLPageRenderer::setTabs($tabs, $linkbase, $active);
	}
	
	private function renderCSV(){
		if (!isset($this->routeInfo["hhp-id"])){
			ErrorHandler::_errorExit("hhp-id nicht gesetzt");
		}
		list($kontoTypes, $data) = $this->fetchBookingHistoryDataFromDB($this->routeInfo["hhp-id"]);
		$csvData = [];
		$header = [
			"id" => "Buchungsnummer",
			"value" => "Betrag in Euro",
			"titel_nr" => "Titelnummer",
			"beleg_name" => "Beleg",
			"datum" => "Buchungsdatum",
			"user" => "Buchender Nutzer",
			"zahlung-name" => "Zahlungsnummer",
			"zahlung-datum" => "Zahlungsdatum",
			"comment" => "Buchungstext",
		];
		foreach ($data as $lfdNr => $row){
			$userStr = isset($row["fullname"]) ? $row["fullname"] . " (" . $row["username"] . ")" : $row["username"];
			$belegStr = "";
			
			switch ($row["beleg_type"]){
				case "belegposten":
					$belegStr = "IP{$row["projekt_id"]} A{$row["auslagen_id"]} - " . $row['short'];
				break;
				case "extern":
					$belegStr = "E{$row["extern_id"]} - V" . $row["vorgang_id"];
				break;
				default:
					ErrorHandler::_errorExit("Unknown beleg_type: " . $row["beleg_type"]);
				break;
			}
			
			$csvData[] = [
				"id" => $row["id"],
				"value" => $row["value"],
				"titel_nr" => $row["titel_nr"],
				"beleg_name" => $belegStr,
				"datum" => $row['timestamp'],
				"user" => $userStr,
				"zahlung-name" => $kontoTypes[$row["zahlung_type"]]["short"] . $row['zahlung_id'],
				"zahlung-datum" => $row["zahlung_date"],
				"comment" => $row['comment'],
			];
		}
		$csvBuilder = new CSVBuilder($csvData, $header);
		$hhps = DBConnector::getInstance()->dbFetchAll("haushaltsplan", [DBConnector::FETCH_UNIQUE_FIRST_COL_AS_KEY]);
		$hhp = $hhps[$this->routeInfo["hhp-id"]];
		$von = date_create($hhp["von"])->format("Y-m");
		$bis = date_create($hhp["bis"])->format("Y-m");
		$csvBuilder->echoCSV(date_create()->format("Y-m-d") . "-Buchungsliste-$von-bis-$bis");
	}
	
	private function renderFullBookingZip(){
		if (!isset($this->routeInfo["hhp-id"])){
			ErrorHandler::_errorExit("hhp-id nicht gesetzt");
		}
		
		$zip = new ZipArchive();
		$zipFileName = "HHA.zip";
		$zipFilePath = tempnam(sys_get_temp_dir(), "HHA");
		
		if (($ret = $zip->open($zipFilePath, ZipArchive::OVERWRITE)) !== true){
			ErrorHandler::_errorExit("Zip kann nicht erstellt werden. ErrorCode: " . $ret);
		}
		
		list($kontoTypes, $data) = $this->fetchBookingHistoryDataFromDB(
			$this->routeInfo["hhp-id"],
			[
				"titel_nr" => true,
				"konto.date" => true,
				"konto.id" => true
			]
		);
		$dataByTitel = [];
		foreach ($data as $id => $row){
			$titelNr = str_replace(" ", "", $row["titel_nr"]);
			$dataByTitel[$titelNr][] = $row;
		}
		
		$header = [
			"id" => "Buchungsnummer",
			"zahlung_date" => "Datum der Zahlung",
			"value" => "Betrag",
			"zahlung" => "Zahlungsnr",
			"einnahmen" => "Einnahmen",
			"ausgaben" => "Ausgaben",
			"beleg_type" => "Belegtyp",
			"comment" => "Buchungstext",
		];
		
		foreach ($dataByTitel as $titel_nr => $items){
			foreach ($items as $key => $row){
				$items[$key]["zahlung"] = $kontoTypes[$row["zahlung_type"]]["short"] . $row["zahlung_id"];
				$items[$key]["einnahmen"] = ($row["zahlung_value"] > 0) ? floatval($row["zahlung_value"]) : "0.00";
				$items[$key]["ausgaben"] = ($row["zahlung_value"] < 0) ? -floatval($row["zahlung_value"]) : "0.00";
				switch ($row["beleg_type"]){
					case "belegposten":
						$items[$key]["beleg_type"] = "Intern";
					break;
					case "extern":
						$items[$key]["beleg_type"] = "Extern";
					break;
					default:
						ErrorHandler::_errorExit($row["beleg_type"] . "kann nicht interpretiert werden");
					break;
				}
			}
			$items[] = [
				"id" => "",
				"zahlung_date" => "Summe",
				"value" => "=SUM(C2:C" . (count($items) + 1) . ")",
				"zahlung" => "",
				"einnahmen" => "=SUM(E2:E" . (count($items) + 1) . ")",
				"ausgaben" => "=SUM(F2:F" . (count($items) + 1) . ")",
				"beleg_type" => "",
				"comment" => "",
			];
			$csvHandler = new CSVBuilder($items, $header);
			$csvString = $csvHandler->getCSV();
			$zip->addFromString($titel_nr . ".csv", $csvString);
		}
		
		if ($zip->close() === true && ($content = file_get_contents($zipFilePath)) !== false){
			header('Content-Type: application/zip');
			header('Content-disposition: attachment; filename=' . $zipFileName);
			header('Content-Length: ' . filesize($zipFileName));
			echo $content;
			unlink($zipFilePath);
		}else{
			echo "Error :(";
		}
	}
	
	private function fetchBookingHistoryDataFromDB($hhp_id, $sortBy = ["timestamp" => true, "id" => true]){
		$kontoTypes = DBConnector::getInstance()->dbFetchAll(
			"konto_type",
			[DBConnector::FETCH_UNIQUE_FIRST_COL_AS_KEY]
		);
		$data = DBConnector::getInstance()->dbFetchAll(
			"booking",
			[DBConnector::FETCH_ASSOC],
			[
				"booking.id",
				"titel_nr",
				"zahlung_id",
				"zahlung_type",
				"zahlung_date" => "konto.date",
				"zahlung_value" => "konto.value",
				"booking.value",
				"beleg_type",
				"canceled",
				"belege.short",
				"auslagen_id",
				"projekt_id",
				"timestamp",
				"username",
				"fullname",
				"kostenstelle",
				"booking.comment",
				"vorgang_id",
				"extern_id",
			],
			["hhp_id" => $hhp_id],
			[
				["type" => "left", "table" => "user", "on" => ["booking.user_id", "user.id"]],
				["type" => "left", "table" => "haushaltstitel", "on" => ["booking.titel_id", "haushaltstitel.id"]],
				[
					"type" => "left",
					"table" => "haushaltsgruppen",
					"on" => ["haushaltsgruppen.id", "haushaltstitel.hhpgruppen_id"]
				],
				[
					"type" => "left",
					"table" => "beleg_posten",
					"on" => [["booking.beleg_id", "beleg_posten.id"], ["booking.beleg_type", "belegposten"]]
				],
				[
					"type" => "left",
					"table" => "belege",
					"on" => [["belege.id", "beleg_posten.beleg_id"], ["booking.beleg_type", "belegposten"]]
				],
				[
					"type" => "left",
					"table" => "auslagen",
					"on" => [["belege.auslagen_id", "auslagen.id"], ["booking.beleg_type", "belegposten"]]
				],
				[
					"type" => "left",
					"table" => "extern_data",
					"on" => [["booking.beleg_id", "extern_data.id"], ["booking.beleg_type", "extern"]],
				],
				[
					"type" => "left",
					"table" => "konto",
					"on" => [["konto.id", "booking.zahlung_id"], ["booking.zahlung_type", "konto.konto_id"]],
				],
			],
			$sortBy
		);
		
		return [$kontoTypes, $data];
	}
	
	private function renderBookingHistory($active){
		list($hhps, $hhp_id) = $this->renderHHPSelector(URIBASE . "booking/", "/history");
		$this->setBookingTabs($active, $hhp_id);
		
		list($kontoTypes, $ret) = $this->fetchBookingHistoryDataFromDB($hhp_id);
		
		if (!empty($ret)){
			//var_dump(reset($ret));
			?>
            <table class="table" align="right">
                <thead>
                <tr>
                    <th>B-Nr</th>
                    <th class="col-xs-1">Betrag (EUR)</th>
                    <th class="col-xs-1">Titel</th>
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
					?>
                    <tr class=" <?= $row["canceled"] != 0 ? "booking__canceled-row" : "" ?>">

                        <td class="no-wrap">
                            <a class="link-anchor" name="<?= $row["id"] ?>"></a><?= $row["id"]/*$lfdNr + 1*/ ?>
                        </td>
                        <td class="money no-wrap <?= TextStyle::BOLD ?>">
							<?= DBConnector::getInstance()->convertDBValueToUserValue($row['value'], "money") ?>
                        </td>
                        <td class="<?= TextStyle::PRIMARY . " " . TextStyle::BOLD ?> no-wrap">
							<?= trim(htmlspecialchars($row['titel_nr'])) ?>
                        </td>
						<?php
						switch ($row["beleg_type"]){
							case "belegposten":
								$projektId = $row["projekt_id"];
								$auslagenId = $row["auslagen_id"];
								echo "<td class='no-wrap'>" . generateLinkFromID(
										"A$auslagenId&nbsp;-&nbsp;B" . $row['short'],
										"projekt/$projektId/auslagen/$auslagenId",
										TextStyle::BLACK
									) . "</td>";
							break;
							case "extern":
								$eId = $row["extern_id"];
								$vId = $row["vorgang_id"];
								/*generateLinkFromID(
									"E$eId&nbsp;-&nbsp;V" . $vId,
									"rest/extern/$eId/$vId",
									TextStyle::BLACK
								);*/
								?>
                                <td class='no-wrap'>
                                    <form method="POST"
                                          action="<?= URIBASE ?>rest/forms/extern/<?= $eId . "/" . $vId ?>/zahlungsanweisung"
                                          class="ajax-form">
										<?= "E" . $eId . " - V" . $vId ?>
                                        <button type='submit' class='btn-link'><i class='fa fa-print'></i></button>
                                        <input type="hidden" name="nonce" value="<?= $GLOBALS["nonce"] ?>">
                                        <input type="hidden" name="d" value="0">
                                    </form>
                                </td>
								<?php
							break;
							default:
								ErrorHandler::_errorExit("Unknown beleg_type: " . $row["beleg_type"]);
						}
						?>
                        <td class="no-wrap">
							<?= date("d.m.Y", strtotime($row['timestamp'])) ?>
                            <i title="<?= $row['timestamp'] . " von " . $userStr ?>"
                               class="fa fa-fw fa-question-circle" aria-hidden="true"></i>
                        </td>

                        <td class="no-wrap"
                            title="<?= "DATUM: " . $row["zahlung_date"] . PHP_EOL . "WERT: " . $row["zahlung_value"] ?>">
							<?=
							generateLinkFromID(
								$kontoTypes[$row["zahlung_type"]]["short"] . $row['zahlung_id'],
								"",
								TextStyle::BLACK
							) ?>
                        </td>
						<?php if ($row["canceled"] == 0){ ?>
                            <td class="no-wrap">
                                <form id="cancel" role="form" action="<?= URIBASE ?>rest/booking/cancel"
                                      method="POST"
                                      enctype="multipart/form-data" class="ajax">
                                    <input type="hidden" name="action" value="cancel-booking"/>
									<?php $this->renderNonce(); ?>
                                    <input type="hidden" name="booking.id" value="<?= $row["id"]; ?>"/>
                                    <input type="hidden" name="hhp.id" value="<?= $hhp_id; ?>"/>

                                    <a href="javascript:void(false);"
                                       class='submit-form <?= TextStyle::DANGER ?>'>
                                        <i class='fa fa-fw fa-ban'></i>&nbsp;Stornieren
                                    </a>
                                </form>
                            </td>
						<?php }else{ ?>
                            <td>Durch <a href='#<?= $row['canceled'] ?>'>B-Nr: <?= $row['canceled'] ?></a></td>
						<?php } ?>
                        <td class="col-xs-4 <?= TextStyle::SECONDARY ?>"><?= htmlspecialchars(
								$row['comment']
							) ?></td>
                    </tr>
				<?php } ?>
                </tbody>
            </table>
            <a class="btn btn-primary"
               target="_blank"
               href="<?= URIBASE ?>export/booking/<?= $hhp_id ?>/csv"
               title="CSV ist WINDOWS-1252 encoded (für Excel optimiert)">
                <i class="fa fa-fw fa-download"></i> als .csv
            </a>
            <a class="btn btn-primary"
               target="_blank"
               href="<?= URIBASE ?>export/booking/<?= $hhp_id ?>/zip"
               title="CSV ist WINDOWS-1252 encoded (für Excel optimiert)">
                <i class="fa fa-fw fa-download"></i> als .zip
            </a>
			<?php
		}else{
			$this->renderAlert("Hinweis","bisher keine Buchungen in diesem HH-Jahr vorhanden.", "info");
		}
	}
	
	private function setKontoTabs($active, $selected_hhp_id){
		$linkbase = URIBASE . "konto/$selected_hhp_id/";
		$tabs = [
			"kasse" => "<i class='fa fa-fw fa-money'></i> Kasse",
			"bank" => "<i class='fa fa-fw fa-credit-card'></i> Bank",
			"sparbuch" => "<i class='fa fa-fw fa-bank'></i> Sparbuch",
		];
		HTMLPageRenderer::setTabs($tabs, $linkbase, $active);
	}
	
	private function renderKonto($activeTab){
		
		list($hhps, $selected_id) = $this->renderHHPSelector(URIBASE . "konto/", "/" . $activeTab);
		$startDate = $hhps[$selected_id]["von"];
		$endDate = $hhps[$selected_id]["bis"];
		switch ($activeTab){
			case "kasse":
				$where = ["konto_id" => 0];
			break;
			case "sparbuch":
				$where = ["konto_id" => 2];
			break;
			default:
				$where = ["konto_id" => ["NOT IN", [0, 2]]];
		}
		if (is_null($endDate) || empty($endDate)){
			$where = array_merge($where, ["date" => [">", $startDate]]);
		}else{
			$where = array_merge($where, ["date" => ["BETWEEN", [$startDate, $endDate]]]);
		}
		
		$alZahlung = DBConnector::getInstance()->dbFetchAll(
			"konto",
			[DBConnector::FETCH_ASSOC],
			[],
			$where,
			[],
			["id" => false]
		);
		$konto_type = DBConnector::getInstance()->dbFetchAll(
			"konto_type",
			[DBConnector::FETCH_UNIQUE_FIRST_COL_AS_KEY]
		);
		
		$this->setKontoTabs($activeTab, $selected_id);
		
		switch ($activeTab){
			case "sparbuch":
			case "bank":
				$this->renderKontoRefreshButton(); ?>
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
					<?php foreach ($alZahlung as $zahlung){
						$prefix = $konto_type[$zahlung["konto_id"]]["short"] ?>
                        <tr title="<?= htmlspecialchars(
							$zahlung["type"] . " - IBAN: " . $zahlung["empf_iban"] . " - BIC: " . $zahlung["empf_bic"] . PHP_EOL . $zahlung["zweck"]
						) ?>">
                            <td><?= htmlspecialchars($prefix . $zahlung["id"]) ?></td>
                            <!-- muss valuta sein - aber nacht Datum wird gefiltert. Das ist so richtig :D -->
                            <td><?= htmlspecialchars($zahlung["valuta"]) ?></td>
                            <td><?= htmlspecialchars($zahlung["empf_name"]) ?></td>
                            <td class="visible-md visible-lg"><?= $this->makeProjektsClickable(
									explode("DATUM", $zahlung["zweck"])[0]
								) ?></td>
                            <td class="visible-md visible-lg"><?= htmlspecialchars($zahlung["empf_iban"]) ?></td>
                            <td class="money"><?= DBConnector::getInstance()->convertDBValueToUserValue(
									$zahlung["value"],
									"money"
								) ?></td>
                            <td class="money"><?= DBConnector::getInstance()->convertDBValueToUserValue(
									$zahlung["saldo"],
									"money"
								) ?></td>
                        </tr>
					<?php } ?>
                    </tbody>
                </table>
				<?php
			break;
			case "kasse":
				$lastId = DBConnector::getInstance()->dbFetchAll(
					"konto",
					[DBConnector::FETCH_ASSOC],
					["max-id" => ["id", DBConnector::GROUP_MAX]],
					["konto_id" => 0]
				)[0]["max-id"];
				?>
                <form action="<?= URIBASE ?>rest/kasse/new" method="POST" class="ajax-form">
					<?php $this->renderNonce(); ?>

                    <table class="table">
                        <thead>
                        <tr>
                            <th class="col-xs-2">Lfd</th>
                            <th>Datum</th>
                            <th class="col-xs-3">Beschreibung</th>
                            <th class="col-xs-2">Betrag</th>
                            <th class="col-xs-2">neues Saldo</th>
                            <th class="col-xs-2">Erstattung / Aktion</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td><input type="number" class="form-control" name="new-nr"
                                       value="<?= isset($lastId) ? $lastId + 1 : 1 ?>" min="1">
                            </td>
                            <td><input type="date" class="form-control" name="new-date"
                                       value="<?= date("Y-m-d") ?>"></td>
                            <td><input type="text" class="form-control" name="new-desc"
                                       placeholder="Text aus Kassenbuch">
                            </td>
                            <td><input type="number" class="form-control" name="new-money" value="0" step="0.01">
                            </td>
                            <td><input type="number" class="form-control" name="new-saldo" value="0" step="0.01">
                            </td>
                            <td>
                                <button type="submit" class="btn btn-success">Speichern</button>
                            </td>
                        </tr>
						<?php
						foreach ($alZahlung as $row){
							$prefix = $konto_type[$row["konto_id"]]["short"];
							echo "<tr>";
							echo "<td>{$prefix}{$row["id"]}</td>";
							echo "<td>" . date_create($row["date"])->format("d.m.Y") . "</td>";
							echo "<td>{$row["type"]} - {$row["zweck"]}</td>";
							echo "<td class='money'>" . DBConnector::getInstance()->convertDBValueToUserValue(
									$row["value"],
									"money"
								) . "</td>";
							echo "<td class='money'>" . DBConnector::getInstance()->convertDBValueToUserValue(
									$row["saldo"],
									"money"
								) . "</td>";
							echo "<td>FIXME</td>";
							echo "</tr>";
						} ?>
                        </tbody>
                    </table>
                </form>
				<?php
			break;
			default:
				ErrorHandler::_errorExit(
					"{$this->routeInfo['action']} kann nicht interpretiert werden - something went horrible wrong!"
				);
			break;
		}
	}
	
	private function renderKontoRefreshButton(){ ?>
        <form action="<?= URIBASE ?>rest/hibiscus" method="POST" role="form"
              class="ajax-form">
            <button type="submit" name="absenden" class="btn btn-primary"
				<?= !(AUTH_HANDLER)::getInstance()->hasGroup("ref-finanzen-kv") ?
					"disabled title='Nur KVs können den Kontoauszug abrufen!'" : "" ?>
            >
                <i class="fa fa-fw fa-refresh"></i> neue Kontoauszüge abrufen
            </button>
            <input type="hidden" name="action" value="hibiscus">
			<?php $this->renderNonce(); ?>
        </form>
		<?php
	}
	
	private function renderBookingText(){
		$btm = new BookingTableManager();
		$btm->render();
	}
	
	private function renderHHPSelector($urlPrefix = URIBASE, $urlSuffix = "/"){
		$hhps = DBConnector::getInstance()->dbFetchAll(
			"haushaltsplan",
			[
				DBConnector::FETCH_ASSOC,
				DBConnector::FETCH_UNIQUE_FIRST_COL_AS_KEY
			],
			[],
			[],
			[],
			["von" => false]
		);
		if (!isset($hhps) || empty($hhps)){
			ErrorHandler::_errorExit("Konnte keine Haushaltspläne finden");
		}
		if (!isset($this->routeInfo["hhp-id"])){
			foreach (array_reverse($hhps, true) as $id => $hhp){
				if ($hhp["state"] === "final"){
					$this->routeInfo["hhp-id"] = $id;
				}
			}
		}
		?>
        <form action="<?= $urlPrefix . $this->routeInfo["hhp-id"] . $urlSuffix ?>"
              data-action='<?= $urlPrefix . "%%" . $urlSuffix ?>'>
            <div class="input-group col-xs-2 pull-right">
                <select class="selectpicker" id="hhp-id"><?php
					foreach ($hhps as $id => $hhp){
						$von = date_create($hhp["von"])->format("M Y");
						$bis = !empty($hhp["bis"]) ? date_create($hhp["bis"])->format("M Y") : false;
						$name = $bis ? $von . " bis " . $bis : "ab " . $von;
						?>
                        <option value="<?= $id ?>" <?= $id == $this->routeInfo["hhp-id"] ? "selected" : "" ?>
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
		return [$hhps, $this->routeInfo["hhp-id"]];
	}
	
	private function renderBooking($active){
		
		list($hhps, $hhp_id) = $this->renderHHPSelector(URIBASE . "booking/", "/instruct");
		$this->setBookingTabs($active, $hhp_id);
		$startDate = $hhps[$hhp_id]["von"];
		$endDate = $hhps[$hhp_id]["bis"];
		
		if (!isset($endDate) || empty($endDate)){
			$fixedWhere = [
				"date" => [">=", $startDate],
			];
		}else{
			$fixedWhere = [
				"date" => ["BETWEEN", [$startDate, $endDate]],
			];
		}
		
		$konto_types = DBConnector::getInstance()->dbFetchAll(
			"konto_type",
			[DBConnector::FETCH_UNIQUE_FIRST_COL_AS_KEY]
		);
		
		$bookedZahlungen = DBConnector::getInstance()->dbFetchAll(
			"booking",
			[DBConnector::FETCH_ASSOC],
			["zahlung" => "zahlung_id", "zahlung_type"],
			["canceled" => 0]
		);
		$instructedZahlung = DBConnector::getInstance()->dbFetchAll(
			"booking_instruction",
			[DBConnector::FETCH_ASSOC],
			["zahlung", "zahlung_type"]
		);
		$tmp = array_merge($bookedZahlungen, $instructedZahlung);
		$excludedZahlung = [];
		foreach ($tmp as $row){
			$excludedZahlung[$row["zahlung_type"]][] = $row["zahlung"];
		}
		
		$where = [];
		foreach (array_keys($konto_types) as $konto_id){
			if (isset($excludedZahlung[$konto_id])){
				$where[] = array_merge(
					$fixedWhere,
					[
						"konto_id" => $konto_id,
						"id" => ["NOT IN", array_unique($excludedZahlung[$konto_id])]
					]
				);
			}else{
				$where[] = array_merge($fixedWhere, ["konto_id" => $konto_id,]);
			}
		}
		
		$alZahlung = DBConnector::getInstance()->dbFetchAll(
			"konto",
			[DBConnector::FETCH_ASSOC],
			[],
			$where,
			[],
			["value" => true]
		);
		
		$instructedAuslagen = DBConnector::getInstance()->dbFetchAll(
			"booking_instruction",
			[DBConnector::FETCH_ONLY_FIRST_COLUMN],
			["beleg"],
			["beleg_type" => "belegposten"]
		);
		if (empty($instructedAuslagen)){
			$instructedAuslagen = [0];
		}
		
		$auslagen = DBConnector::getInstance()->dbFetchAll(
			"auslagen",
			[DBConnector::FETCH_ASSOC],
			[
				"auslagen.*",
				"projekte.name",
				"ausgaben" => ["beleg_posten.ausgaben", DBConnector::GROUP_SUM_ROUND2],
				"einnahmen" => ["beleg_posten.einnahmen", DBConnector::GROUP_SUM_ROUND2]
			],
			[
				"auslagen.id" => ["NOT IN", $instructedAuslagen],
				"auslagen.state" => ["LIKE", "instructed%"]
			],
			[
				["type" => "inner", "table" => "projekte", "on" => ["projekte.id", "auslagen.projekt_id"]],
				["type" => "inner", "table" => "belege", "on" => ["belege.auslagen_id", "auslagen.id"]],
				["type" => "inner", "table" => "beleg_posten", "on" => ["beleg_posten.beleg_id", "belege.id"]],
			],
			["einnahmen" => true],
			["auslagen.id"]
		);
		array_walk(
			$auslagen,
			function(&$auslage){
				$auslage["value"] = floatval($auslage["einnahmen"]) - floatval($auslage["ausgaben"]);
				$auslage["type"] = "auslage";
			}
		);
		
		$instructedExtern = DBConnector::getInstance()->dbFetchAll(
			"booking_instruction",
			[DBConnector::FETCH_ONLY_FIRST_COLUMN],
			["beleg"],
			["beleg_type" => "extern"]
		);
		
		if (empty($instructedExtern)){
			$instructedExtern = [-1]; //-1 cannot exist as id, but will not sql error with NOT IN (-1)
		}
		
		$extern = DBConnector::getInstance()->dbFetchAll(
			"extern_data",
			[DBConnector::FETCH_ASSOC],
			[
				"id" => "extern_data.id",
				"vorgang_id",
				"extern_id",
				"projekt_name",
				"org_name",
				"value",
				"flag_vorkasse",
				"flag_pruefbescheid",
				"flag_rueckforderung"
			],
			[
				[
					"flag_vorkasse" => 1,
					"state_payed" => ["IS NOT", null],
					"state_booked" => ["IS", null],
					"extern_data.id" => ["NOT IN", $instructedExtern],
					"value" => ["<>", 0],
				],
				[
					"flag_pruefbescheid" => 1,
					"state_payed" => ["IS NOT", null],
					"state_booked" => ["IS", null],
					"extern_data.id" => ["NOT IN", $instructedExtern],
					"value" => ["<>", 0],
				],
				[
					"flag_rueckforderung" => 1,
					"state_booked" => ["IS", null],
					"flag_widersprochen" => 0,
					"extern_data.id" => ["NOT IN", $instructedExtern],
					"value" => ["<>", 0],
				],
				[
					"flag_rueckforderung" => 1,
					"state_payed" => ["IS NOT", null],
					"state_booked" => ["IS", null],
					"extern_data.id" => ["NOT IN", $instructedExtern],
					"value" => ["<>", 0],
				],
			],
			[
				["type" => "inner", "table" => "extern_meta", "on" => ["extern_meta.id", "extern_data.extern_id"]],
			]
		);
		foreach ($extern as $k => $row){
			$vz = ($row["flag_vorkasse"] == "1") ? -1 : (($row["flag_pruefbescheid"] == "1") ? -1 : 1);
			$extern[$k]["value"] = $vz * floatval($row["value"]);
			$extern[$k]["type"] = "extern";
		}
		$alGrund = array_merge($auslagen, $extern);
		//sort with reverse order
		usort(
			$alGrund,
			function($e1, $e2){
				if ($e1["value"] === $e2["value"]){
					return 0;
				}else if ($e1["value"] > $e2["value"]){
					return 1;
				}else{
					return -1;
				}
			}
		);
		$this->renderKontoRefreshButton();
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
						$value = min(
							[floatval($alZahlung[$idxZahlung]["value"]), $alGrund[$idxGrund]["value"]]
						);
					}else{
						//var_dump($alZahlung[$idxZahlung]);
						$value = floatval($alZahlung[$idxZahlung]["value"]);
					}
				}else{
					$value = $alGrund[$idxGrund]["value"];
				}
				echo "<td>";
				
				while (isset($alZahlung[$idxZahlung]) && floatval($alZahlung[$idxZahlung]["value"]) === $value){
					echo "<input type='checkbox' class='booking__form-zahlung' data-value='{$value}' data-id='{$alZahlung[$idxZahlung]["id"]}' data-type='{$alZahlung[$idxZahlung]["konto_id"]}'>";
					
					//print_r($alZahlung[$idxZahlung]);
					if ($alZahlung[$idxZahlung]['konto_id'] == 0){
						$caption = "K{$alZahlung[$idxZahlung]['id']} - {$alZahlung[$idxZahlung]["type"]} - {$alZahlung[$idxZahlung]["zweck"]}";
						$title = "BELEG: {$alZahlung[$idxZahlung]["comment"]}" . PHP_EOL . "DATUM: {$alZahlung[$idxZahlung]["date"]}";
					}else{
						$title = "VALUTA: " . $alZahlung[$idxZahlung]["valuta"] . PHP_EOL . "IBAN: " . $alZahlung[$idxZahlung]["empf_iban"] . PHP_EOL . "BIC: " . $alZahlung[$idxZahlung]["empf_bic"];
						$caption = $konto_types[$alZahlung[$idxZahlung]["konto_id"]]["short"];
						$caption .= $alZahlung[$idxZahlung]['id'] . " - ";
						switch ($alZahlung[$idxZahlung]["type"]){
							case "FOLGELASTSCHRIFT":
								$caption .= "LASTSCHRIFT an ";
							break;
							case "ONLINE-UEBERWEISUNG":
								$caption .= "ÜBERWEISUNG an ";
							break;
							case "UEBERWEISUNGSGUTSCHRIFT":
							case "GUTSCHRIFT":
								$caption .= "GUTSCHRIFT von ";
							break;
							default: //Buchung, Entgeldabschluss,KARTENZAHLUNG...
								$caption .= $alZahlung[$idxZahlung]["type"] . " an ";
							break;
						}
						$caption .= $alZahlung[$idxZahlung]["empf_name"] . " - " .
							explode("DATUM", $alZahlung[$idxZahlung]["zweck"])[0];
					}
					
					$url = str_replace("//", "/", URIBASE . "/zahlung/" . $alZahlung[$idxZahlung]["id"]);
					echo "<a href='" . htmlspecialchars($url) . "' title='" . htmlspecialchars(
							$title
						) . "'>" . htmlspecialchars($caption) . "</a>";
					$idxZahlung++;
					echo "<br>";
				}
				echo "</td><td class='money'>";
				echo DBConnector::getInstance()->convertDBValueToUserValue($value, "money");
				echo "</td><td>";
				while (isset($alGrund[$idxGrund]) && $alGrund[$idxGrund]["value"] === $value){
					
					switch ($alGrund[$idxGrund]["type"]){
						case "auslage":
							echo "<input type='checkbox' class='booking__form-beleg' data-value='{$value}' data-type='auslage'
data-id='{$alGrund[$idxGrund]['id']}'>";
							$caption = "A" . $alGrund[$idxGrund]["id"] . " - " . $alGrund[$idxGrund]["name"] . " - " . $alGrund[$idxGrund]["name_suffix"];
							$url = str_replace(
								"//",
								"/",
								URIBASE . "/projekt/{$alGrund[$idxGrund]['projekt_id']}/auslagen/" . $alGrund[$idxGrund]["id"]
							);
						break;
						case "extern":
							echo "<input type='checkbox' class='booking__form-beleg' data-value='{$value}' data-type='extern' data-id='{$alGrund[$idxGrund]['id']}' data-v-id='{$alGrund[$idxGrund]['vorgang_id']}' data-e-id='{$alGrund[$idxGrund]['id']}'>";
							$caption = "E" . $alGrund[$idxGrund]["extern_id"] . "-V" . $alGrund[$idxGrund]["vorgang_id"] .
								" - " . $alGrund[$idxGrund]["projekt_name"] . " - " . $alGrund[$idxGrund]["org_name"];
							$url = str_replace(
								"//",
								"/",
								URIBASE . "/extern/" . $alGrund[$idxGrund]["extern_id"]
							);
						break;
						default:
							ErrorHandler::_errorExit("Type " . $alGrund[$idxGrund]["type"] . " not known");
						break;
					}
					
					echo "<a href='" . htmlspecialchars($url) . "'>" . $caption . "</a>";
					$idxGrund++;
					echo "<br>";
				}
				echo "</td>";
				echo "</tr>";
			}
			
			?>
        </table>
        <!--<form id="instruct-booking" role="form" action="<?= URIBASE ?>rest/booking/cancel" method="POST"
                                  enctype="multipart/form-data" class="ajax">-->
        <form action="<?= URIBASE ?>rest/booking/instruct" method="POST" role="form" class="ajax-form">
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
				<?php $this->renderNonce(); ?>
                <button type="submit" id="booking__check-button"
                        class="btn btn-primary  <?= (AUTH_HANDLER)::getInstance()->hasGroup(
							"ref-finanzen-hv"
						) ? "" : "user-is-not-hv" ?>"
					<?= (AUTH_HANDLER)::getInstance()->hasGroup("ref-finanzen-hv") ? "" : "disabled" ?>>
                    Buchung anweisen
                </button>
            </div>
        </form>
		
		<?php
	}
	
	
}