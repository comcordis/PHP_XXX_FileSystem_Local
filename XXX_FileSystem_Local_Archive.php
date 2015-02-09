<?php

class XXX_FileSystem_Local_Archive
{
	////////////////////
	// TarGzip / Zip
	////////////////////
	
		// tar -zcvf /path/to/archive.tar.gz /path/to/input1 /path/to/input2 /path/to/input3
		// c = create a new archive
		// f = use archive file
		// v = verbose
		// z = gzip it
		
		public static function createTarGzipArchive ($archiveFilePath = '', $sourcePath = '')
		{
			$result = false;
			
			if (XXX_FileSystem_Local::ensurePathExistenceByDestination($archiveFilePath))
			{			
				$tarGzipCommand = '';
				
				$tarGzipCommand = 'tar -zcvf';
				
				$tarGzipCommand .= ' ' . $archiveFilePath;
				
				if (XXX_Type::isArray($sourcePath))
				{				
					$tarGzipCommand .= ' ' . XXX_Array::joinValuesToString($sourcePath, ' ');
				}
				else
				{
					$tarGzipCommand .= ' ' . $sourcePath;
				}
				
				$commandResponse = XXX_CommandLineHelpers::executeCommand($tarGzipCommand);
								
				if ($commandResponse)
				{
					if ($commandResponse['statusCode'] == 0)
					{
						$result = true;
					}
				}
			}
			
			return $result;
		}
		
		// tar -zxvf /path/to/archive.tar.gz
		// x = extract
		
		public static function extractTarGzipArchive ($archiveFilePath = '', $destinationPath = '')
		{
			$result = false;
			
			if ($destinationPath == '' || XXX_FileSystem_Local::ensurePathExistence($destinationPath))
			{			
				$tarGzipCommand = '';
				
				$tarGzipCommand = 'tar -zxvf';
				
				$tarGzipCommand .= ' ' . $archiveFilePath;
				
				$tarGzipCommand .= ' ' . $destinationPath;
				
				$commandResponse = XXX_CommandLineHelpers::executeCommand($tarGzipCommand);
								
				if ($commandResponse)
				{
					if ($commandResponse['statusCode'] == 0)
					{
						$result = true;
					}
				}
			}
			
			return $result;
		}
		
		// Windows: http://stahlworks.com/dev/?tool=zipunzip

		// zip -r /path/to/archive.zip /path/to/input1 /path/to/input2 /path/to/input3
		// zip -e -r -P password /path/to/archive.zip /path/to/input1 /path/to/input2 /path/to/input3
		// -e = encrypt
		// -r = recursive
		// -P = password		
		
		public static function createZipArchive ($archiveFilePath = '', $sourcePath = '', $password = '')
		{
			$result = false;
			
			if (XXX_FileSystem_Local::ensurePathExistenceByDestination($archiveFilePath))
			{			
				$zipCommand = '';
				
				if ($password != '')
				{
					$zipCommand = 'zip -e -r -P ' . $password;
				}
				else
				{
					$zipCommand = 'zip -r';
				}
				
				$zipCommand .= ' ' . $archiveFilePath;
				
				if (XXX_Type::isArray($sourcePath))
				{				
					$zipCommand .= ' ' . XXX_Array::joinValuesToString($sourcePath, ' ');
				}
				else
				{
					$zipCommand .= ' ' . $sourcePath;
				}
				
				$commandResponse = XXX_CommandLineHelpers::executeCommand($zipCommand);
				
				if ($password != '')
				{
					XXX_CommandLineHelpers::clearHistory();
				}
				
				if ($commandResponse)
				{
					if ($commandResponse['statusCode'] == 0)
					{
						$result = true;
					}
				}
			}
			
			return $result;
		}
		
		// unzip /path/to/archive.zip -d /path/to/destination
		// unzip -P password /path/to/archive.zip -d /path/to/destination
		// -d = destination directory to unzip in
		// -P = password
		
		public static function extractZipArchive ($archiveFilePath = '', $destinationPath = '', $password = '')
		{
			$result = false;
			
			if ($destinationPath == '' || XXX_FileSystem_Local::ensurePathExistence($destinationPath))
			{			
				$zipCommand = '';
				
				if ($password != '')
				{
					$zipCommand = 'unzip -P ' . $password;
				}
				else
				{
					$zipCommand = 'unzip';
				}
				
				$zipCommand .= ' ' . $archiveFilePath;
				
				if ($destinationPath != '')
				{
					$zipCommand .= ' -d ' . $destinationPath;
				}
				
				$commandResponse = XXX_CommandLineHelpers::executeCommand($zipCommand);
				
				if ($password != '')
				{
					XXX_CommandLineHelpers::clearHistory();
				}
				
				if ($commandResponse)
				{
					if ($commandResponse['statusCode'] == 0)
					{
						$result = true;
					}
				}
			}
			
			return $result;
		}
	
	public static function determineExtension ()
	{
		$result = '.tar.gz';

		if (XXX_OperatingSystem::$platformName == 'windows')
		{
			$result = '.zip';
		}

		return $result;
	}

	public static function createArchive ($archiveFilePath = '', $sourcePath = '', $password = '')
	{
		$result = false;
		
		if (XXX_OperatingSystem::$platformName == 'windows')
		{
			$result = self::createZipArchive($archiveFilePath, $sourcePath, $password);
		}
		else
		{
			$result = self::createTarGzipArchive($archiveFilePath, $sourcePath);
		}
		
		return $result;
	}
	
	public static function extractArchive ($archiveFilePath = '', $destinationPath = '', $password = '')
	{
		$result = false;
		
		if (XXX_OperatingSystem::$platformName == 'windows')
		{
			$result = self::extractZipArchive($archiveFilePath, $destinationPath, $password);
		}
		else
		{
			$result = self::extractTarGzipArchive($archiveFilePath, $destinationPath);
		}
		
		return $result;
	}
}

?>