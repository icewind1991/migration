<?php
/**
 * Copyright (c) 2017 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

/** @var $this OC\Route\Router */

return ['routes' => [
	['name' => 'settings#checkRemote', 'url' => '/check', 'verb' => 'GET'],
	['name' => 'settings#checkCredentials', 'url' => '/check_credentials', 'verb' => 'POST'],
	['name' => 'settings#migrate', 'url' => '/migrate', 'verb' => 'GET'] // EventSource only supports GET
]];
