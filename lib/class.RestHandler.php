<?php
/**
 * FRAMEWORK JsonHandler
 *
 * @package           Stura - Referat IT - ProtocolHelper
 * @category          framework
 * @author            michael g
 * @author            Stura - Referat IT <ref-it@tu-ilmenau.de>
 * @since             17.02.2018
 * @copyright         Copyright (C) 2018 - All rights reserved
 * @platform          PHP
 * @requirements      PHP 7.0 or higher
 */
include_once dirname(__FILE__) . '/class.JsonController.php';

class RestHandler
	extends EscFunc{
	
	// ================================================================================================
	
	/**
	 * private class constructor
	 * implements singleton pattern
	 */
	public function __construct(){
		$this->json_result = [];
	}
	
	// ================================================================================================
	
	/**
	 *
	 * @param array $routeInfo
	 */
	public function handlePost($routeInfo = null){
		global $nonce;
		if (!isset($_POST["nonce"]) || $_POST["nonce"] !== $nonce || isset($_POST["nononce"])){
			ErrorHandler::_renderError('Access Denied - you send the wrong form.', 403);
		}else{
			unset($_POST["nonce"]);
		}
		
		switch ($routeInfo['action']){
			case 'projekt':
				$this->handleProjekt($routeInfo);
			break;
			case 'auslagen':
				$this->handleAuslagen($routeInfo);
			break;
			case 'extern':
				$this->handleExtern($routeInfo);
			break;
			case 'chat':
				$this->handleChat($routeInfo);
			break;
			case 'update-konto':
				$this->updateKonto($routeInfo);
			break;
			case "new-booking-instruct":
				$this->newBookingInstruct($routeInfo);
			break;
            case "delete-booking-instruct":
                $this->deleteBookingInstruction($routeInfo);
                break;
            case "cancel-booking":
				$this->cancelBooking($routeInfo);
			break;
            case "confirm-instruct":
				$this->saveConfirmedBookingInstruction();
			break;
            case "save-new-kasse-entry":
				$this->saveNewKasseEntry();
			break;
            case "mirror":
                $this->mirrorInput();
                break;
			case 'nononce':
			default:
				ErrorHandler::_errorExit('Unknown Action: ' . $routeInfo['action']);
			break;
		}
	}

	private function mirrorInput(){
        JsonController::print_json(
            [
                'success' => true,
                'status' => '200',
                'msg' => var_export($_POST,true),
                'type' => 'modal',
                'subtype' => 'server-warning',
                'headline' => 'Mirror des Inputs'
            ]
        );
    }
	
	private function handleExtern($routeInfo){
		$extHandler = new ExternVorgangHandler($routeInfo);
		$extHandler->handlePost();
	}
	
	public function saveNewKasseEntry(){
		$fields = [];
		$fields["id"] = strip_tags($_REQUEST["new-nr"]);
		$fields["konto_id"] = 0;
		$fields["date"] = strip_tags($_REQUEST["new-date"]);
		$fields["valuta"] = strip_tags($_REQUEST["new-date"]);
		$fields["zweck"] = strip_tags($_REQUEST["new-desc"]);
		$fields["value"] = floatval(strip_tags($_REQUEST["new-money"]));
		$fields["saldo"] = floatval(strip_tags($_REQUEST["new-saldo"]));
		if (strlen($fields["zweck"]) < 5){
			JsonController::print_json(
				[
					'success' => false,
					'status' => '200',
					'msg' => "Der Beschreibungstext muss mindestens 5 Zeichen lang sein!",
					'type' => 'modal',
					'subtype' => 'server-error',
					'headline' => 'Fehler bei der Verarbeitung'
				]
			);
		}
		$fields["type"] = "BAR-" . ($fields["value"] > 0 ? "EIN" : "AUS");
		if ($fields["id"] === "1"){
			DBConnector::getInstance()->dbInsert("konto", $fields);
		}else{
			$last = DBConnector::getInstance()->dbFetchAll(
				"konto",
				[DBConnector::FETCH_ASSOC],
				[],
				[
					"konto_id" => 0,
					"id" =>
						$fields["id"] - 1
				]
			)[0];
			if (abs($last["saldo"] + $fields["value"] - $fields["saldo"]) < 0.01){
				DBConnector::getInstance()->dbInsert("konto", $fields);
			}else{
				JsonController::print_json(
					[
						'success' => false,
						'status' => '200',
						'msg' => "Alter Saldo und neuer Saldo passen nicht zusammen",
						'type' => 'modal',
						'subtype' => 'server-error',
						'headline' => 'Fehler bei der Verarbeitung'
					]
				);
			}
		}
		JsonController::print_json(
			[
				'success' => true,
				'status' => '200',
				'msg' => 'Die Seite wird gleich neu geladen',
				'type' => 'modal',
				'subtype' => 'server-success',
				'reload' => 1000,
				'headline' => 'Erfolgreich gespeichert'
			]
		);
	}
	
	public function handleProjekt($routeInfo = null){
		$ret = false;
		$msgs = [];
		$projektHandler = null;
		$dbret = false;
		try{
			$auth = (AUTH_HANDLER);
			/* @var $auth AuthHandler */
			$auth = $auth::getInstance();
			$logId = DBConnector::getInstance()->logThisAction($_POST);
			DBConnector::getInstance()->logAppend($logId, "username", $auth->getUsername());
			
			if (!isset($_POST["action"]))
				throw new ActionNotSetException("Es wurde keine Aktion übertragen");
			
			if (DBConnector::getInstance()->dbBegin() === false)
				throw new PDOException("cannot start DB transaction");
			
			switch ($_POST["action"]){
				case "create":
					$projektHandler = ProjektHandler::createNewProjekt($_POST);
					if ($projektHandler !== null)
						$ret = true;
				break;
				case "changeState":
					if (!isset($_POST["id"]) || !is_numeric($_POST["id"])){
						throw new IdNotSetException("ID nicht gesetzt.");
					}
					$projektHandler = new ProjektHandler(["pid" => $_POST["id"], "action" => "none"]);
					$ret = $projektHandler->setState($_POST["newState"]);
				break;
				case "update":
					if (!isset($_POST["id"]) || !is_numeric($_POST["id"])){
						throw new IdNotSetException("ID nicht gesetzt.");
					}
					$projektHandler = new ProjektHandler(["pid" => $_POST["id"], "action" => "edit"]);
					$ret = $projektHandler->updateSavedData($_POST);
				break;
				default:
					throw new ActionNotSetException("Unbekannte Aktion verlangt!");
			}
		}catch (ActionNotSetException $exception){
			$ret = false;
			$msgs[] = $exception->getMessage();
		}catch (IdNotSetException $exception){
			$ret = false;
			$msgs[] = $exception->getMessage();
		}catch (WrongVersionException $exception){
			$ret = false;
			$msgs[] = $exception->getMessage();
		}catch (IllegalStateException $exception){
			$ret = false;
			$msgs[] = "In diesen Status darf nicht gewechselt werden!";
			$msgs[] = $exception->getMessage();
		}catch (OldFormException $exception){
			$ret = false;
			$msgs[] = "Bitte lade das Projekt neu!";
			$msgs[] = $exception->getMessage();
		}catch (InvalidDataException $exception){
			$ret = false;
			$msgs[] = $exception->getMessage();
		}catch (PDOException $exception){
			$ret = false;
			$msgs[] = $exception->getMessage();
		}catch (IllegalTransitionException $exception){
			$ret = false;
			$msgs[] = $exception->getMessage();
		}finally{
			if ($ret)
				$dbret = DBConnector::getInstance()->dbCommit();
			if ($ret === false || $dbret === false){
				DBConnector::getInstance()->dbRollBack();
				$msgs[] = "Deine Änderungen wurden nicht gespeichert (DB Rollback)";
			}else{
				$msgs[] = "Daten erfolgreich gespeichert!";
				$target = URIBASE . "projekt/" . $projektHandler->getID();
			}
			if (isset($logId)){
				DBConnector::getInstance()->logAppend($logId, "result", $ret);
				DBConnector::getInstance()->logAppend($logId, "msgs", $msgs);
			}else{
				$msgs[] = "Logging nicht möglich :(";
			}
			
			if (isset($projektHandler))
				DBConnector::getInstance()->logAppend($logId, "projekt_id", $projektHandler->getID());
		}
		if (DEV)
			$msgs[] = print_r($_POST, true);

		$json = [
            'success' => ($ret !== false),
            'status' => '200',
            'msg' => $msgs,
            'type' => 'modal',

        ];
		if(isset($target)){
            $json['redirect'] = $target;
        }
		if($ret === false){
            $json["subtype"] = "server-error";
        }else{
            $json["reload"] = 1000;
            $json["subtype"] = "server-success";
        }

		JsonController::print_json($json);
	}
	
	/**
	 * handle auslagen posts
	 *
	 * @param string $routeInfo
	 */
	public function handleAuslagen($routeInfo = null){
		$func = '';
		if (!isset($routeInfo['mfunction'])){
			if (isset($_POST['action'])){
				$routeInfo['mfunction'] = $_POST['action'];
			}else{
				ErrorHandler::_renderError('No Action and mfunction.', 404);
			}
		}
		
		//validate
		$vali = new Validator();
		$validator_map = [];
		switch ($routeInfo['mfunction']){
			case 'updatecreate':
				$validator_map = [
					'version' => [
						'integer',
						'min' => '1',
						'error' => 'Ungültige Versionsnummer.'
					],
					'etag' => [
						'regex',
						'pattern' => '/^(0|([a-f0-9]){32})$/',
						'error' => 'Ungültige Version.'
					],
					'projekt-id' => [
						'integer',
						'min' => '1',
						'error' => 'Ungültige Projekt ID.'
					],
					'auslagen-id' => [
						'regex',
						'pattern' => '/^(NEW|[1-9]\d*)$/',
						'error' => 'Ungültige Auslagen ID.'
					],
					'auslagen-name' => [
						'regex',
						'pattern' => '/^[a-zA-Z0-9\-_ :,;%$§\&\+\*\.!\?\/\\\[\]\'"#~()äöüÄÖÜéèêóòôáàâíìîúùûÉÈÊÓÒÔÁÀÂÍÌÎÚÙÛß]*$/',
						'maxlength' => '255',
						'minlength' => '2',
						'error' => 'Ungültiger oder leerer Auslagen name.'
					],
					'zahlung-name' => [
						'regex',
						'pattern' => '/^[a-zA-Z0-9\-_ :,;%$§\&\+\*\.!\?\/\\\[\]\'"#~()äöüÄÖÜéèêóòôáàâíìîúùûÉÈÊÓÒÔÁÀÂÍÌÎÚÙÛß]*$/',
						'maxlength' => '127',
						'empty',
						'error' => 'Ungültiger Zahlungsempfänger.'
					],
					'zahlung-iban' => [
						'regex',
						'pattern' => '/^(([a-zA-Z]{2}\s*\d{2}\s*([0-9a-zA-Z]{4}\s*){4}[0-9a-zA-Z]{2})|([a-zA-Z0-9]{4}( ... ... )[a-zA-Z0-9]{2}))$/',
						'maxlength' => '127',
						'empty',
						'error' => 'Ungültige Iban.'
					],
					'zahlung-vwzk' => [
						'regex',
						'pattern' => '/^[a-zA-Z0-9\-_,$§:;\/\\\\()!?& \.\[\]%\'"#~\*\+äöüÄÖÜéèêóòôáàâíìîúùûÉÈÊÓÒÔÁÀÂÍÌÎÚÙÛß]*$/',
						'empty',
						'maxlength' => '127',
					],
					'address' => [
						'regex',
						'pattern' => '/^[a-zA-Z0-9\-_,:;\/\\\\()& \n\r\.\[\]%\'"#\*\+äöüÄÖÜéèêóòôáàâíìîúùûÉÈÊÓÒÔÁÀÂÍÌÎÚÙÛß]*$/',
						'empty',
						'maxlength' => '1023',
						'error' => 'Adressangabe fehlerhaft.',
					],
					'belege' => [
						'array',
						'optional',
						'minlength' => 1,
						'key' => [
							'regex',
							'pattern' => '/^(new_)?(\d+)$/'
						],
						'validator' => [
							'arraymap',
							'required' => true,
							'map' => [
								'datum' => [
									'date',
									'empty',
									'format' => 'Y-m-d',
									'parse' => 'Y-m-d',
									'error' => 'Ungültiges Beleg Datum.'
								],
								'beschreibung' => [
									'text',
									'strip',
									'trim',
								],
								'posten' => [
									'array',
									'optional',
									'minlength' => 1,
									'key' => [
										'regex',
										'pattern' => '/^(new_)?(\d+)$/'
									],
									'validator' => [
										'arraymap',
										'required' => true,
										'map' => [
											'projekt-posten' => [
												'integer',
												'min' => '1',
												'error' => 'Invalid Projektposten ID.'
											],
											'in' => [
												'float',
												'step' => '0.01',
												'format' => '2',
												'min' => '0',
												#'error' => 'Posten - Einnahmen: Ungültiger Wert'
											],
											'out' => [
												'float',
												'step' => '0.01',
												'format' => '2',
												'min' => '0',
												#'error' => 'Posten - Ausgaben: Ungültiger Wert'
											],
										]
									]
								]
							]
						]
					],
				];
			break;
			case 'filedelete':
				$validator_map = [
					'etag' => [
						'regex',
						'pattern' => '/^(0|([a-f0-9]){32})$/',
						'error' => 'Ungültige Version.'
					],
					'projekt-id' => [
						'integer',
						'min' => '1',
						'error' => 'Ungültige Projekt ID.'
					],
					'auslagen-id' => [
						'integer',
						'min' => '1',
						'error' => 'Ungültige Auslagen ID.'
					],
					'fid' => [
						'integer',
						'min' => '1',
						'error' => 'Ungültige Datei ID.'
					],
				];
			break;
			case 'state':
				$auslagen_states = [];
				$validator_map = [
					'etag' => [
						'regex',
						'pattern' => '/^(0|([a-f0-9]){32})$/',
						'error' => 'Ungültige Version.'
					],
					'projekt-id' => [
						'integer',
						'min' => '1',
						'error' => 'Ungültige Projekt ID.'
					],
					'auslagen-id' => [
						'integer',
						'min' => '1',
						'error' => 'Ungültige Auslagen ID.'
					],
					'state' => [
						'regex',
						'pattern' => '/^(draft|wip|ok|instructed|booked|revocation|payed|ok-hv|ok-kv|ok-belege|revoked|rejected)$/',
						'error' => 'Ungültiger Status.'
					],
				];
			break;
			case 'belegpdf':
				$auslagen_states = [];
				$validator_map = [
					'projekt-id' => [
						'integer',
						'min' => '1',
						'error' => 'Ungültige Projekt ID.'
					],
					'auslagen-id' => [
						'integer',
						'min' => '1',
						'error' => 'Ungültige Auslagen ID.'
					],
					'd' => [
						'integer',
						'optional',
						'min' => '0',
						'max' => '1',
						'error' => 'Ungültige Parameter.'
					],
				];
			break;
			case "zahlungsanweisung":
				$auslagen_states = [];
				$validator_map = [
					'projekt-id' => [
						'integer',
						'min' => '1',
						'error' => 'Ungültige Projekt ID.'
					],
					'auslagen-id' => [
						'integer',
						'min' => '1',
						'error' => 'Ungültige Auslagen ID.'
					],
					'd' => [
						'integer',
						'optional',
						'min' => '0',
						'max' => '1',
						'error' => 'Ungültige Parameter.'
					],
				];
			break;
			default:
				ErrorHandler::_renderError('Unknown Action.', 404);
			break;
		}
		$vali->validateMap($_POST, $validator_map, true);
		//return error if validation failed
		if ($vali->getIsError()){
			JsonController::print_json(
				[
					'success' => false,
					'status' => '200',
					'msg' => $vali->getLastErrorMsg(),
					'type' => 'validator',
					'field' => $vali->getLastMapKey(),
				]
			);
		}
		
		$validated = $vali->getFiltered();
		
		if ($routeInfo['mfunction'] == 'updatecreate'){
			//may add nonexisting arrays
			if (!isset($validated['belege'])){
				$validated['belege'] = [];
			}
			foreach ($validated['belege'] as $k => $v){
				if (!isset($v['posten'])){
					$validated['belege'][$k]['posten'] = [];
				}
			}
			//check all values empty?
			$empty = ($validated['auslagen-id'] == 'NEW');
			$auslagen_test_empty = [
				'auslagen-name',
				'zahlung-name',
				'zahlung-iban',
				'zahlung-vwzk',
				'belege',
				'address'
			];
			$belege_test_empty = ['datum', 'beschreibung', 'posten'];
			$posten_text_empty = ['out', 'in'];
			if ($empty)
				foreach ($auslagen_test_empty as $e){
					if (is_string($validated[$e]) && !!$validated[$e]
						|| is_array($validated[$e]) && count($validated[$e])){
						$empty = false;
						break;
					}
				}
			if ($empty)
				foreach ($validated['belege'] as $kb => $belege){
					foreach ($belege_test_empty as $e){
						if (is_string($belege[$e]) && !!$belege[$e]
							|| is_array($belege[$e]) && count($belege[$e])){
							$empty = false;
							break 2;
						}
					}
					foreach ($belege['posten'] as $posten){
						foreach ($posten_text_empty as $e){
							if (is_string($posten[$e]) && !!$posten[$e]
								|| is_array($posten[$e]) && count($posten[$e])){
								$empty = false;
								break 3;
							}
						}
					}
					
					//check file non empty
					$fileIdx = 'beleg_' . $kb;
					if (isset($_FILES[$fileIdx]['error']) && $_FILES[$fileIdx]['error'] === 0){
						$empty = false;
						break;
					}
				}
			//error reply
			if ($empty){
				JsonController::print_json(
					[
						'success' => false,
						'status' => '200',
						'msg' => 'Leere Auslagenerstattungen können nicht gespeichert werden.',
						'type' => 'modal',
						'subtype' => 'server-error',
					]
				);
			}
		}
		$routeInfo['pid'] = $validated['projekt-id'];
		if ($validated['auslagen-id'] != 'NEW'){
			$routeInfo['aid'] = $validated['auslagen-id'];
		}
		$routeInfo['validated'] = $validated;
		$routeInfo['action'] = 'post';
		//call auslagen handler
		$handler = new AuslagenHandler2($routeInfo);
		$handler->handlePost();
		
		//error reply
		if ($empty){
			JsonController::print_json(
				[
					'success' => false,
					'status' => '200',
					'msg' => 'Der Posthandler hat die Anfrage nicht beantwortet.',
					'type' => 'modal',
					'subtype' => 'server-error',
				]
			);
		}
	}
	
	private function handleChat($routeInfo){
		$db = DBConnector::getInstance();
		$chat = new ChatHandler(null, null);
		$valid = $chat->validatePost($_POST);
		$auth = (AUTH_HANDLER);
		/* @var $auth AuthHandler */
		$auth = $auth::getInstance();
		if ($valid){
			//access permission control
			switch ($valid['target']){
				case 'projekt':
					{
						$r = [];
						try{
							$r = $db->dbFetchAll(
								'projekte',
								[DBConnector::FETCH_ASSOC],
								['projekte.*', 'user.username', 'user.email'],
								['projekte.id' => $valid['target_id']],
								[
									["type" => "left", "table" => "user", "on" => [["user.id", "projekte.creator_id"]]],
								]
							);
						}catch (Exception $e){
							ErrorHandler::_errorLog('RestHandler:  ' . $e->getMessage());
							break;
						}
						if (!$r || count($r) == 0){
							break;
						}
						$pdate = date_create(substr($r[0]['createdat'], 0, 4) . '-01-01 00:00:00');
						$pdate->modify('+1 year');
						$now = date_create();
						//info mail
						$mail = [];
						// ACL --------------------------------
						// action
						switch ($valid['action']){
							case 'gethistory':
								$map = ['0', '1'];
								if ($auth->hasGroup('admin')){
									$map[] = '2';
								}
								if ($auth->hasGroup('ref-finanzen')){
									$map[] = '3';
								}
								if ($auth->hasGroup(
										'ref-finanzen'
									) || isset($r[0]['username']) && $r[0]['username'] == $auth->getUsername()){
									$map[] = '-1';
								}
								$chat->setKeep($map);
							break;
							case 'newcomment':
								//allow chat only 90 days into next year
								if ($now->getTimestamp() - $pdate->getTimestamp() > 86400 * 90){
									break 2;
								}
								//new message - info mail
								$tMail = [];
								if (!preg_match(
									'/^(draft|wip|revoked|ok-by-hv|need-stura|done-hv|done-other|ok-by-stura)/',
									$r[0]['state']
								)){
									break 2;
								}
								//switch type
								switch ($valid['type']){
									case '-1':
										if (!$auth->hasGroup(
												'ref-finanzen'
											) && (!isset($r[0]['username']) || $r[0]['username'] != $auth->getUsername(
												))){
											break 3;
										}
										if (!$auth->hasGroup('ref-finanzen')){
											$tMail['to'][] = 'ref-finanzen@tu-ilmenau.de';
										}else{
											$tMail['to'][] = $r[0]['email'];
										}
									break;
									case '0':
										if (!$auth->hasGroup('ref-finanzen')){
											$tMail['to'][] = 'ref-finanzen@tu-ilmenau.de';
										}else{
											$tMail['to'][] = $r[0]['responsible'];
										}
									break;
									case '2':
										if (!$auth->hasGroup('admin')){
											break 3;
										}
									break;
									case '3':
										if (!$auth->hasGroup('ref-finanzen')){
											break 3;
										}
										$tMail['to'][] = 'ref-finanzen@tu-ilmenau.de';
									break;
									default:
									break 3;
								}
								if (count($tMail) > 0){
									$tMail['param']['msg'][] = 'Im %Projekt% #' . $r[0]['id'] . ' gibt es eine neue Nachricht.';
									$tMail['param']['link']['Projekt'] = BASE_URL . URIBASE . 'projekt/' . $r[0]['id'] . '#projektchat';
									$tMail['param']['headline'] = 'Projekt - Neue Nachricht';
									$tMail['subject'] = 'Stura-Finanzen: Neue Nachricht in Projekt #' . $r[0]['id'];
									$tMail['template'] = 'projekt_default';
									$mail[] = $tMail;
								}
							break;
							default:
							break 2;
						}
						// all ok -> handle all
						$chat->answerAll($_POST);
						if (count($mail) > 0){
							$mh = MailHandler::getInstance();
							foreach ($mail as $m){
								//create and send email
								$mail_result = $mh->easyMail($m);
							}
						}
						die();
					}
				break;
				case 'auslagen':
					{
						$r = [];
						try{
							$r = $db->dbFetchAll(
								'auslagen',
								[DBConnector::FETCH_ASSOC],
								['auslagen.*'],
								['auslagen.id' => $valid['target_id']],
								[]
							);
						}catch (Exception $e){
							ErrorHandler::_errorLog('RestHandler:  ' . $e->getMessage());
							break;
						}
						if (!$r || count($r) == 0){
							break;
						}
						$pdate = date_create(substr($r[0]['created'], 0, 4) . '-01-01 00:00:00');
						$pdate->modify('+1 year');
						$now = date_create();
						//info mail
						$mail = [];
						// ACL --------------------------------
						// action
						switch ($valid['action']){
							case 'gethistory':
								$map = ['1'];
								if ($auth->hasGroup('ref-finanzen')){
									$map[] = '3';
								}
								$tmpsplit = explode(';', $r[0]['created']);
								if ($auth->hasGroup('ref-finanzen') || $tmpsplit[1] == $auth->getUsername()){
									$map[] = '-1';
								}
								$chat->setKeep($map);
							break;
							case 'newcomment':
								//allow chat only 90 days into next year
								if ($now->getTimestamp() - $pdate->getTimestamp() > 86400 * 90){
									break 2;
								}
								//new message - info mail
								$tMail = [];
								//switch type
								switch ($valid['type']){
									case '-1':
										if (!$auth->hasGroup('ref-finanzen') && $auth->getUsername(
											) != AuslagenHandler2::state2stateInfo('wip;' . $r[0]['created'])['user']){
											break 3;
										}
										if (!$auth->hasGroup('ref-finanzen')){
											$tMail['to'][] = 'ref-finanzen@tu-ilmenau.de';
										}else{
											$u = $db->dbFetchAll(
												'user',
												[DBConnector::FETCH_ASSOC],
												['email', 'id'],
												[
													'username' => AuslagenHandler2::state2stateInfo(
														'wip;' . $r[0]['created']
													)['user']
												]
											);
											if ($u && count($u) > 0){
												$tMail['to'][] = $u[0]['email'];
											}
										}
									break;
									case '3':
										if (!$auth->hasGroup('ref-finanzen')){
											break 3;
										}
										$tMail['to'][] = 'ref-finanzen@tu-ilmenau.de';
									break;
									default:
									break 3;
								}
								if (count($tMail) > 0){
									$tMail['param']['msg'][] = 'In der %Abrechnung% #' . $r[0]['id'] . ' gibt es eine neue Nachricht.';
									$tMail['param']['link']['Abrechnung'] = BASE_URL . URIBASE . 'projekt/' . $r[0]['projekt_id'] . '/auslagen/' . $r[0]['id'] . '#auslagenchat';
									$tMail['param']['headline'] = 'Auslagen - Neue Nachricht';
									$tMail['subject'] = 'Stura-Finanzen: Neue Nachricht in Abrechnung #' . $r[0]['id'];
									$tMail['template'] = 'projekt_default';
									$mail[] = $tMail;
								}
							break;
							default:
							break 2;
						}
						// all ok -> handle all
						$chat->answerAll($_POST);
						if (count($mail) > 0){
							$mh = MailHandler::getInstance();
							foreach ($mail as $m){
								//create and send email
								$mail_result = $mh->easyMail($m);
							}
						}
						die();
					}
				break;
				default:
				break;
			}
		}
		$chat->setErrorMessage('Access Denied.');
		$chat->answerError();
		die();
	}
	
	private function updateKonto($routeInfo){
		$auth = (AUTH_HANDLER);
		/* @var $auth AuthHandler */
		$auth = $auth::getInstance();
		$auth->requireGroup(HIBISCUSGROUP);
		
		$ret = true;
		if (!DBConnector::getInstance()->dbBegin()){
			ErrorHandler::_errorExit(
				"Kann keine Verbindung zur SQL-Datenbank aufbauen. Bitte versuche es später erneut!"
			);
		}
		list($success, $msg_xmlrpc, $allZahlungen) = HibiscusXMLRPCConnector::getInstance()->fetchAllUmsatz();
		
		if ($success === false){
			JsonController::print_json(
				[
					'success' => false,
					'status' => '500',
					'msg' => 'Konnte keine Verbindung mit Onlinebanking Service aufbauen',
					'type' => 'modal',
					'subtype' => 'server-error',
				]
			);
		}
		/*$lastId = DBConnector::getInstance()->dbFetchAll(
			"konto",
			[DBConnector::FETCH_ASSOC],
			["id" => ["id", DBConnector::GROUP_MAX]]
		);
		if (is_array($lastId)){
			$lastId = $lastId[0]["id"];
		}*/
		$msg = [];
		$inserted = [];
		foreach ($allZahlungen as $zahlung){
			$fields = [];
			$fields['id'] = $zahlung["id"];
			$fields['konto_id'] = $zahlung["konto_id"];
			$fields['date'] = $zahlung["datum"];
			$fields['type'] = $zahlung["art"];
			$fields['valuta'] = $zahlung["valuta"];
			$fields['primanota'] = $zahlung["primanota"];
			$fields['value'] = DBConnector::getInstance()->convertUserValueToDBValue($zahlung["betrag"], "money");
			$fields['empf_name'] = $zahlung["empfaenger_name"];
			$fields['empf_iban'] = $zahlung["empfaenger_konto"];
			$fields['empf_bic'] = $zahlung["empfaenger_blz"];
			$fields['saldo'] = $zahlung["saldo"];
			$fields['gvcode'] = $zahlung["gvcode"];
			$fields['zweck'] = $zahlung["zweck"];
			$fields['comment'] = $zahlung["kommentar"];
			$fields['customer_ref'] = $zahlung["customer_ref"];
			//$msgs[]= print_r($zahlung,true);
			DBConnector::getInstance()->dbInsert("konto", $fields);
			if (isset($inserted[$zahlung["konto_id"]])){
				$inserted[$zahlung["konto_id"]]++;
			}else{
				$inserted[$zahlung["konto_id"]] = 1;
			}
			
			$matches = [];
			if (preg_match("/IP-[0-9]{2,4}-[0-9]+-A[0-9]+/", $zahlung["zweck"], $matches)){
				$beleg_sum = 0;
				$ahs = [];
				foreach ($matches as $match){
					$arr = explode("-", $match);
					$auslagen_id = substr(array_pop($arr), 1);
					$projekt_id = array_pop($arr);
					$ah = new AuslagenHandler2(["pid" => $projekt_id, "aid" => $auslagen_id, "action" => "none"]);
					$pps = $ah->getBelegPostenFiles();
					foreach ($pps as $pp){
						foreach ($pp["posten"] as $posten){
							if ($posten["einnahmen"]){
								$beleg_sum += $posten["einnahmen"];
							}
							if ($posten["ausgaben"]){
								$beleg_sum -= $posten["ausgaben"];
							}
						}
					}
					$ahs[] = $ah;
				}
				if (abs($beleg_sum - $fields['value']) < 0.01){
					foreach ($ahs as $ah){
						$ret = $ah->state_change("payed", $ah->getAuslagenEtag());
						if ($ret !== true){
							$msg[] = "Konnte IP" . $ah->getProjektID() . "-A" . $ah->getID() .
								" nicht in den Status 'gezahlt' überführen. " .
								"Bitte ändere das noch (per Hand) nachträglich!" .
								$fields['date'];
						}
					}
				}else{
					$msg[] = "In Zahlung " . $zahlung["id"] . " wurden folgende Projekte/Auslagen im Verwendungszweck gefunden: " . implode(
							" & ",
							$matches
						) . ". Dort stimmt die Summe der Belegposten (" . $beleg_sum . ") nicht mit der Summe der Zahlung (" . $fields['value'] . ") überein. Bitte prüfe das noch per Hand, und setze ggf. die passenden Projekte auf bezahlt, so das es später keine Probleme beim Buchen gibt (nur gezahlte Auslagen können gebucht werden)";
				}
			}
		}
		
		$ret = DBConnector::getInstance()->dbCommit();
		
		if (!$ret){
			DBConnector::getInstance()->dbRollBack();
			JsonController::print_json(
				[
					'success' => false,
					'status' => '500',
					'msg' => array_merge($msg_xmlrpc, $msg),
					'type' => 'modal',
					'subtype' => 'server-error',
					'headline' => 'Ein Datenbank Fehler ist aufgetreten! (Rollback)'
				]
			);
		}else{
			if (!empty($inserted)){
				$type = (count($msg_xmlrpc) + count($msg)) > 1 ? "warning" : "success";
				foreach ($inserted as $konto_id => $number){
					$msg = array_merge(["$number neue Umsätze auf Konto $konto_id gefunden und hinzugefügt!"], $msg);
				}
				JsonController::print_json(
					[
						'success' => true,
						'status' => '200',
						'msg' => array_merge($msg_xmlrpc, $msg),
						'type' => 'modal',
						'subtype' => 'server-' . $type,
					]
				);
			}else{
				$msg = array_merge(['Keine neuen Umsätze gefunden.'], $msg);
				JsonController::print_json(
					[
						'success' => false,
						'status' => '200',
						'msg' => array_merge($msg_xmlrpc, $msg),
						'type' => 'modal',
						'subtype' => 'server-warning',
					]
				);
			}
		}
	}
	
	private function deleteBookingInstruction($routeInfo){
        $instructId = $routeInfo["instruct-id"];
        $res = DBConnector::getInstance()->dbDelete("booking_instruction", ["id" => $instructId]);
        if($res > 0){
            JsonController::print_json(
                [
                    'success' => true,
                    'status' => '200',
                    'msg' => "Vorgang $instructId wurde zurückgesetzt.",
                    'type' => 'modal',
                    'subtype' => 'server-success',
                    'headline' => 'Erfolgreiche Datenübertragtung',
                    'reload' => 2000,
                ]
            );
        }else{
            JsonController::print_json(
                [
                    'success' => false,
                    'status' => '500',
                    'msg' => "Vorgang $instructId konnte nicht gefunden werden!",
                    'type' => 'modal',
                    'subtype' => 'server-error',
                    'headline' => 'Fehler bei der Datenübertragung',
                ]
            );
        }
    }

	private function newBookingInstruct($routeInfo){
		$errorMsg = [];
		$zahlung = isset($_POST["zahlung"]) ? $_POST["zahlung"] : [];
		$zahlung_type = isset($_POST["zahlung-type"]) ? $_POST["zahlung-type"] : [];
		$auslage = isset($_POST["auslage"]) ? $_POST["auslage"] : [];
		$externDataId = isset($_POST["extern"]) ? $_POST["extern"] : [];
		
		if (count($zahlung_type) !== count($zahlung)){
			$errorMsg[] = "Ungleiche Datenübertragung bei Zahlung, falls neu laden (Strg + F5) nichts hilft, kontaktiere bitte den Administrator.";
		}
		if ((count($zahlung) > 1 && (count($auslage) + count($externDataId)) > 1)
			|| (count($auslage) === 0 && count($externDataId) === 0)
			|| count($zahlung) === 0
		){
			$errorMsg[] = "Es kann immer nur 1 Zahlung zu n Belegen oder 1 Beleg zu n Zahlungen zugeordnet werden. Andere Zuordnungen sind nicht möglich!";
		}
		$where = [];
		if (count($auslage) > 0){
			$where[] = ["canceled" => 0, "belege.auslagen_id" => ["IN", $auslage]];
		}
		if (count($externDataId) > 0){
			$where[] = ["canceled" => 0, "extern_data.id" => ["IN", $externDataId]];
		}
		
		//check if allready booked
		$bookingDBbelege = DBConnector::getInstance()->dbFetchAll(
			"booking",
			[DBConnector::FETCH_ASSOC],
			["booking.beleg_id"],
			$where,
			[
				[
					"table" => "beleg_posten",
					"type" => "left",
					"on" => [["beleg_posten.id", "booking.beleg_id"], ["booking.beleg_type", "belegposten"]]
				],
				["table" => "belege", "type" => "inner", "on" => ["belege.id", "beleg_posten.beleg_id"]],
				[
					"table" => "extern_data",
					"type" => "left",
					"on" => [["extern_data.id", "booking.belegposten_id"], ["booking.beleg_type", "extern"]]
				],
			]
		);
		
		$zahlungByType = [];
		foreach ($zahlung as $key => $item){
			$zahlungByType[$zahlung_type[$key]] = $item;
		}
		$where = [];
		foreach ($zahlungByType as $type => $zahlungsArray){
			$where[] = ["canceled" => 0, "zahlung_id" => ["IN", $zahlung], "zahlung_type" => $type];
		}
		
		$bookingDBzahlung = DBConnector::getInstance()->dbFetchAll(
			"booking",
			[DBConnector::FETCH_ASSOC],
			["zahlung_id", "zahlung_type"],
			$where
		);
		
		if (count($bookingDBbelege) + count($bookingDBzahlung) > 0){
			$errorMsg[] = "Beleg oder Zahlung bereits verknüpft - " . print_r(
					array_merge($bookingDBzahlung, $bookingDBbelege),
					true
				);
		}
		
		if (!empty($errorMsg)){
			JsonController::print_json(
				[
					'success' => false,
					'status' => '500',
					'msg' => $errorMsg,
					'type' => 'modal',
					'subtype' => 'server-error',
					'headline' => 'Fehler bei der Datenübertragung',
				]
			);
		}
		DBConnector::getInstance()->dbBegin();
		$nextId = DBConnector::getInstance()->dbFetchAll(
			"booking_instruction",
			[DBConnector::FETCH_NUMERIC],
			[["id", DBConnector::GROUP_MAX]]
		);
		if (is_array($nextId) && !empty($nextId)){
			$nextId = $nextId[0][0] + 1;
		}else{
			$nextId = 1;
		}
		foreach ($zahlung as $zId => $zahl){
			foreach ($auslage as $bel){
				DBConnector::getInstance()->dbInsert(
					"booking_instruction",
					[
						"id" => $nextId,
						"zahlung" => $zahl,
						"zahlung_type" => $zahlung_type[$zId],
						"beleg" => $bel,
						"beleg_type" => "belegposten",
						"by_user" => DBConnector::getInstance()->getUser()["id"]
					]
				);
			}
			foreach ($externDataId as $ext){
				DBConnector::getInstance()->dbInsert(
					"booking_instruction",
					[
						"id" => $nextId,
						"zahlung" => $zahl,
						"zahlung_type" => $zahlung_type[$zId],
						"beleg" => $ext,
						"beleg_type" => "extern",
						"by_user" => DBConnector::getInstance()->getUser()["id"]
					]
				);
			}
		}
		if (DBConnector::getInstance()->dbCommit()){
			JsonController::print_json(
				[
					'success' => true,
					'status' => '200',
					'msg' => "Buchung wurde angewiesen",
					'type' => 'modal',
					'subtype' => 'server-success',
					'reload' => 1000,
					'headline' => 'Erfolgreich gespeichert',
				]
			);
		}else{
			DBConnector::getInstance()->dbRollBack();
			JsonController::print_json(
				[
					'success' => false,
					'status' => '500',
					'msg' => "Fehler bei der Übertragung zur Datenbank",
					'type' => 'modal',
					'subtype' => 'server-error',
					'headline' => 'Fehler',
				]
			);
		}
		
	}
	
	private function saveConfirmedBookingInstruction(){
		//var_dump($_POST);
		$confirmedInstructions = array_keys($_REQUEST["activeInstruction"]);
		$text = $_REQUEST["text"];

		if(empty($confirmedInstructions)){
            JsonController::print_json(
                [
                    'success' => true,
                    'status' => '200',
                    'msg' => 'Es wurde kein Vorgang ausgewählt.',
                    'type' => 'modal',
                    'subtype' => 'server-warning',
                    //'reload' => 2000,
                    'headline' => 'Fehlerhafte Eingabe',
                ]
            );
        }
		
		$btm = new BookingTableManager($confirmedInstructions);
		$btm->run();
		
		$zahlungenDB = $btm->getZahlungDB();
		$belegeDB = $btm->getBelegeDB();
		/* $belegPostenDB = DBConnector::getInstance()->dbFetchAll(
			"auslagen",
			[DBConnector::FETCH_ASSOC],
			[
				"auslagen.id",
				"auslagen.projekt_id",
				"titel_id",
				"titel_type" => "haushaltsgruppen.type",
				"posten_id" => "beleg_posten.id",
				"beleg_posten.einnahmen",
				"beleg_posten.ausgaben",
				"etag",
			],
			["auslagen.id" => ["IN", $belege]],
			[
				["table" => "belege", "type" => "inner", "on" => ["belege.auslagen_id", "auslagen.id"]],
				["table" => "beleg_posten", "type" => "inner", "on" => ["beleg_posten.beleg_id", "belege.id"]],
				[
					"table" => "projektposten",
					"type" => "inner",
					"on" => [
						["projektposten.id", "beleg_posten.projekt_posten_id"],
						["auslagen.projekt_id", "projektposten.projekt_id"]
					],
				],
				[
					"table" => "haushaltstitel",
					"type" => "inner",
					"on" => ["projektposten.titel_id", "haushaltstitel.id"]
				],
				[
					"table" => "haushaltsgruppen",
					"type" => "inner",
					"on" => ["haushaltsgruppen.id", "haushaltstitel.hhpgruppen_id"]
				],
			]
		);*/
		//start write action
		DBConnector::getInstance()->dbBegin();
		//check if transferable to new States (payed => booked)
		$stateChangeNotOk = [];
		$doneAuslage = [];
		foreach ($confirmedInstructions as $instruction){
			foreach ($belegeDB[$instruction] as $beleg){
				switch ($beleg["type"]){
					case "auslage":
						$ah = new AuslagenHandler2(
							[
								"aid" => $beleg["auslagen_id"],
								"pid" => $beleg["projekt_id"],
								"action" => "none"
							]
						);
						if (!in_array("A" . $beleg["auslagen_id"], $doneAuslage)
							&& $ah->state_change_possible("booked") !== true){
							
							$stateChangeNotOk[] = "IP-" . date_create($beleg["projekt_createdate"])->format("y") . "-" .
								$beleg["projekt_id"] . "-A" . $beleg["auslagen_id"] . " (" . $ah->getStateString(
								) . ")";
						}else{
							$ah->state_change("booked", $beleg["etag"]);
							$doneAuslage[] = "A" . $beleg["auslagen_id"];
						}
					break;
					case "extern":
						$evh = new ExternVorgangHandler($beleg["id"]);
						
						if (!in_array("E" . $beleg["id"], $doneAuslage)
							&& $evh->state_change_possible("booked") !== true){
							$stateChangeNotOk[] = "EP-" .
								$beleg["extern_id"] . "-V" . $beleg["vorgang_id"] . " (" . $evh->getStateString() .
								")";
						}else{
							$evh->state_change("booked", $beleg["etag"]);
							$doneAuslage[] = "E" . $beleg["id"];
						}
					
					break;
				}
			}
		} // transfered states to booked - otherwise throw error
		if (!empty($stateChangeNotOk)){
			DBConnector::getInstance()->dbRollBack();
			JsonController::print_json(
				[
					'success' => false,
					'status' => '500',
					'msg' => array_merge(
						["Folgende Projekte lassen sich nicht von bezahlt in gebucht überführen: "],
						$stateChangeNotOk
					),
					'type' => 'modal',
					'subtype' => 'server-error',
					//'reload' => 2000,
					'headline' => 'Konnte nicht gespeichert werden',
				]
			);
		}
		
		$zahlung_sum = array_fill_keys($confirmedInstructions, 0);
		$belege_sum = array_fill_keys($confirmedInstructions, 0);
		$table = $btm->getTable();
		// sammle werte pro instruction auf
		foreach ($confirmedInstructions as $instruction){
			foreach ($table[$instruction] as $row){
				if($row["titel"]["type"] === "1"){
					$belege_sum[$instruction] -= floatval($row["posten-ist"]["val-raw"]);	
				}else{
					$belege_sum[$instruction] += floatval($row["posten-ist"]["val-raw"]);	
				}
				if (isset($row["zahlung-value"])){
					$zahlung_sum[$instruction] += floatval($row["zahlung-value"]["val-raw"]);
				}
			}
		}
		foreach ($confirmedInstructions as $instruction){
            if (count($table[$instruction]) !== count($text[$instruction])){
                DBConnector::getInstance()->dbRollBack();
                JsonController::print_json(
                    [
                        'success' => false,
                        'status' => '500',
                        'msg' => "Falsche Daten wurden übvertragen - Textfelder fehlen bei Vorgang $instruction",
                        'type' => 'modal',
                        'subtype' => 'server-error',
                        'reload' => 2000,
                        'headline' => 'Konnte nicht gespeichert werden',
                    ]
                );
            }
        }

		
		foreach ($confirmedInstructions as $instruction){
			//check if algorithm  was correct :'D
			$diff = abs($zahlung_sum[$instruction] - $belege_sum[$instruction]);
			if ($diff >= 0.01){
				DBConnector::getInstance()->dbRollBack();
				JsonController::print_json(
					[
						'success' => false,
						'status' => '500',
						'msg' => "Falsche Daten wurden übvertragen: Differenz der Posten = $diff (Vorgang $instruction)",
						'type' => 'modal',
						'subtype' => 'server-error',
						//'reload' => 2000,
						'headline' => 'Konnte nicht gespeichert werden',
					]
				);
			}
		}
		
		$maxBookingId = DBConnector::getInstance()->dbFetchAll(
			"booking",
			[DBConnector::FETCH_UNIQUE_FIRST_COL_AS_KEY],
			[["id", DBConnector::GROUP_MAX]]
		);
		$maxBookingId = array_keys($maxBookingId)[0];
		
		//save in booking-list
		$table = $btm->getTable(true);

		foreach ($confirmedInstructions as $instruction){
            $idx = 0;
            $bookingText = array_values($text[$instruction]);
            foreach ($table[$instruction] as $row){
                DBConnector::getInstance()->dbInsert(
                    "booking",
                    [
                        "id" => ++$maxBookingId,
                        "titel_id" => $row["titel"]["val-raw"],
                        "zahlung_id" => $row["zahlung"]["val-raw"],
                        "zahlung_type" => $row["zahlung"]["zahlung-type"],
                        "beleg_id" => $row["posten"]["val-raw"],
                        "beleg_type" => $row["beleg"]["beleg-type"],
                        "user_id" => DBConnector::getInstance()->getUser()["id"],
                        "comment" => $bookingText[$idx++],
                        "value" => $row["posten-ist"]["val-raw"],
                        "kostenstelle" => 0,
                    ]
                );
			}
		}
		
		//delete from instruction list
		DBConnector::getInstance()->dbUpdate("booking_instruction", ["id" => ["IN", $confirmedInstructions]], ["done" => 1]);
		DBConnector::getInstance()->dbCommit();
		JsonController::print_json(
			[
				'success' => true,
				'status' => '200',
				'msg' => "Die Seite wird gleich neu geladen",
				'type' => 'modal',
				'subtype' => 'server-success',
				'reload' => 2000,
				'headline' => count($confirmedInstructions) . (count($confirmedInstructions) >= 1 ? ' Vorgänge ': ' Vorgang ') . ' erfolgreich gespeichert',
			]
		);
	}
	
	private function cancelBooking($routeInfo){
		
		(AUTH_HANDLER)::getInstance()->requireGroup(HIBISCUSGROUP);
		if (!isset($_REQUEST["booking_id"])){
			$msgs[] = "Daten wurden nicht korrekt übermittelt";
			die();
		}
		$booking_id = $_REQUEST["booking_id"];
		$ret = DBConnector::getInstance()->dbFetchAll("booking", [DBConnector::FETCH_ASSOC], [], ["id" => $booking_id]);
		$maxBookingId = DBConnector::getInstance()->dbFetchAll(
			"booking",
			[DBConnector::FETCH_ONLY_FIRST_COLUMN],
			[["id", DBConnector::GROUP_MAX]],
			["id" => $booking_id]
		)[0];
		if ($ret !== false && !empty($ret)){
			$ret = $ret[0];
			if ($ret["canceled"] !== 0){
				
				DBConnector::getInstance()->dbBegin();
				$user_id = DBConnector::getInstance()->getUser()["id"];
				DBConnector::getInstance()->dbInsert(
					"booking",
					[
						"id" => $maxBookingId + 1,
						"comment" => "Rotbuchung zu B-Nr: " . $booking_id,
						"titel_id" => $ret["titel_id"],
						"belegposten_id" => $ret["belegposten_id"],
						"zahlung_id" => $ret["zahlung_id"],
						"kostenstelle" => $ret["kostenstelle"],
						"user_id" => $user_id,
						"value" => -$ret["value"], //negative old Value
						"canceled" => $booking_id,
					]
				);
				DBConnector::getInstance()->dbUpdate(
					"booking",
					["id" => $booking_id],
					["canceled" => $maxBookingId + 1]
				);
				if (!DBConnector::getInstance()->dbCommit()){
					DBConnector::getInstance()->dbRollBack();
					JsonController::print_json(
						[
							'success' => false,
							'status' => '500',
							'msg' => "Ein Server fehler ist aufgetreten",
							'type' => 'modal',
							'subtype' => 'server-error',
							//'reload' => 2000,
							'headline' => 'Konnte nicht gespeichert werden',
						]
					);
				}else{
					JsonController::print_json(
						[
							'success' => true,
							'status' => '200',
							'msg' => "Wurde erfolgreich gegengebucht; die Seite wird gleich neu geladen.",
							'type' => 'modal',
							'subtype' => 'server-success',
							'reload' => 1000,
							'headline' => 'Daten gespeichert',
						]
					);
				}
			}
		}
	}
}
