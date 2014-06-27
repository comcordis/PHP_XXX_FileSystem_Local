<?php

require_once 'XXX_FileSystem_Local.php';
require_once 'XXX_FileSystem_Local_Archive.php';
require_once 'XXX_Log.php';

XXX::addEventListener('beforeExecutionExit', 'XXX_Log::saveBuffers');
?>