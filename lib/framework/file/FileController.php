<?php
/**
 * CONTROLLER FileHandler
 *
 * @category        framework
 * @author 			michael g
 * @author 			Stura - Referat IT <ref-it@tu-ilmenau.de>
 * @since 			08.05.2018
 * @copyright 		Copyright (C) 2018 - All rights reserved
 * @platform        PHP
 * @requirements    PHP 7.0 or higher
 */

namespace framework\file;

use framework\DBConnector;
use framework\render\ErrorHandler;

class FileController
{
    /**
     * @var DBConnector
     */
    private $db;

    /**
     * constructor
     */
    public function __construct()
    {
        $this->db = DBConnector::getInstance();
    }

    public function handle($routeInfo): void
    {
        if ($routeInfo['action'] === 'get') {
            if (!isset($routeInfo['fdl'])) {
                $routeInfo['fdl'] = 0;
            }
            $this->get($routeInfo);
        }
    }

    /**
     * ACTION get
     * handle file delivery
     */
    private function get($routeInfo): void
    {
        $fh = new FileHandler($this->db);
        //get file
        $file = $fh->checkFileHash($routeInfo['key']);
        if (!$file) {
            ErrorHandler::handleError(404);
            return;
        }
        //TODO FIXME ACL - user has permission to download/view this file?
        if (false) {//!checkUserPermission($top['gname'])) {
            ErrorHandler::handleError(403);
            exit();
        }
        $fh->deliverFileData($file, $routeInfo['fdl']);
        return;
    }
}