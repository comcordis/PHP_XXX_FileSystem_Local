<?php

abstract class XXX_Log
{
	public static $buffers = array
	(
		'development' => ''
	);
		
	public static function saveBuffer ($log = 'development')
	{
		$result = false;
		
		if (XXX_Array::hasKey(self::$buffers, $log))
		{
			$buffer = self::$buffers[$log];
			
			if ($buffer == '')
			{
				$result = true;
			}
			else
			{			
				$logFile = XXX_Path_Local::$deploymentDataPathPrefix . 'logs' . XXX_OperatingSystem::$directorySeparator . $log . '.log';
				
				$result = XXX_FileSystem_Local::appendFileContent($logFile, $buffer);
				
				if ($result)
				{
					self::$buffers[$log] = '';
				}
			}
		}
		
		return $result;
	}
	
	public static function saveBuffers ()
	{
		$result = false;
		
		$savedBuffers = true;
		
		foreach (self::$buffers as $log => $buffer)
		{
			$tempResult = self::saveBuffer($log);
			
			if (!$tempResult)
			{
				$savedBuffers = false;
			}
		}
		
		if ($savedBuffers)
		{
			$result = true;
		}
		
		return $result;
	}
		
	public static function append ($line = '', $log = 'development')
	{
		$result = false;
		
		if (!XXX_Array::hasKey(self::$buffers, $log))
		{
			self::$buffers[$log] = '';
		}
		
		self::$buffers[$log] .= $line;
		
		$result = true;
		
		return $result;
	}
	
	public static function appendLine ($line = '', $log = 'development')
	{
		$result = false;
		
		$line .= XXX_String::$lineSeparator;
		
		$result = self::append($line, $log);
		
		return $result;
	}
	
	public static function logLine ($lineContent = '', $log = 'development', $timestamp = null, $prefixDate = true)
	{
		$line = '';
		
		$lineContent = XXX_String::normalizeLineSeparators($lineContent);
		$lineContent = XXX_String::replace($lineContent, XXX_String::$lineSeparator, ' ');
		
		if ($prefixDate)
		{
			$line .= self::getTimestamp($timestamp) . ' ';
		}
		
		$line .= $lineContent;
		
		return self::appendLine($line, $log);
	}
	
	public static function getTimestamp ($timestamp = null)
	{
		$timestamp = new XXX_Timestamp($timestamp);
		
		$timestampParts = $timestamp->parse();
		
		$result = $timestampParts['iso8601'];
		
		return $result;
	}
	
	public static function logRuler ($log = 'development')
	{
		$line = '--------------------------------------------------------------------------';
		
		return self::appendLine($line, $log);
	}
	
	public static function resetLog ($log = 'development')
	{
		$result = false;
		
		if (XXX_Array::hasKey(self::$buffers, $log))
		{
			self::$buffers[$log] = '';;
			
			$logFile = XXX_Path_Local::$deploymentDataPathPrefix . 'logs' . XXX_OperatingSystem::$directorySeparator . $log . '.log';
			
			$result = XXX_FileSystem_Local::writeFileContent($logFile, '');
		}
		
		return $result;
	}
}

?>