<?php

/*

TODO regression test

path shouldn't be:
- empty
- . (current)
- .. (parent)


Path = Full
Identifier = fileName.fileExtension or directoryName
Name = file
Extension .ext

*/

abstract class XXX_FileSystem_Local
{
	const CLASS_NAME = 'XXX_FileSystem_Local';
	
	public static $mimeMagicPath = '';
	
	public static $settings = array
	(
		'defaultPermissions' => array
		(
			'file' => '660',
			'directory' => '770'
		)
	);
	
	public static function initialize (array $settings)
	{
		self::$settings = XXX_Array::merge(self::$settings, $settings);
	}
	
	////////////////////
	// Merged files content
	////////////////////
	
	// Prefix, suffix filter to for example strip <?php etc.
	public static function getMergedFilesContent ($path = '', $files = array(), $glue = '', $prefixFilterPattern = false, $suffixFilterPattern = false)
	{
		$mergedContent = '';
		
		if (XXX_Type::isArray($files))
		{
			for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal($files); $i < $iEnd; ++$i)
			{
				$tempPath = XXX_Path_Local::extendPath($path, $files[$i]);
				
				$fileContent = self::getFileContent($tempPath);
				
				if ($prefixFilterPattern)
				{
					$fileContent = XXX_String_Pattern::replace($fileContent, $prefixFilterPattern[0], $prefixFilterPattern[1], '');
				}
				
				if ($suffixFilterPattern)
				{
					$fileContent = XXX_String_Pattern::replace($fileContent, $suffixFilterPattern[0], $suffixFilterPattern[1], '');
				}
				
				if ($glue && $i > 0)
				{
					$fileContent = $glue . $fileContent;
				}
				
				$mergedContent .= $fileContent;
			}
		}
		
		return $mergedContent;
	}
	
	////////////////////
	// General
	////////////////////
	
	public static function doesIdentifierExist ($path = '')
	{
		$result = file_exists($path);
		
		return $result;
	}
	
	public static function isNonSystemIdentifier ($identifier = '')
	{
		$result = false;
		
		if (!($identifier === '' || $identifier === '.' || $identifier === '..'))
		{
			$result = true;
		}
		
		return $result;
	}
	
	public static function ensurePathExistence ($path = '')
	{
		$result = false;
		
		$pathExistencePaths = XXX_Path_Local::getPathExistencePaths($path);
		
		if (XXX_Array::getFirstLevelItemTotal($pathExistencePaths) > 0)
		{
			for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal($pathExistencePaths); $i < $iEnd; ++$i)
			{
				$tempPath = $pathExistencePaths[$i];
								
				// Presuming the parent of the deepest level exists anyway, it's safe to try and create the deepest directory
				if (!self::doesDirectoryExist($tempPath) && $i == $iEnd - 1)
				{
					if (!self::createDirectory($tempPath))
					{
						$result = false;
						break;
					}
				}
								
				// Find the deepest one that exists
				if (self::doesDirectoryExist($tempPath))
				{
					if ($i == 0)
					{
						$result = true;
					}
					else if ($i > 0)
					{
						$result = true;
						
						// Now traverse reversed from this point...			
						for ($j = $i - 1, $jEnd = 0; $j >= $jEnd; --$j)
						{
							$tempPath = $pathExistencePaths[$j];
							
							if (!self::createDirectory($tempPath))
							{
								$result = false;
								break;
							}
						}
					}
					
					break;
				}				
			}
		}
		else
		{
			if (self::doesDirectoryExist($path))
			{		
				$result = true;
			}
		}
				
		return $result;
	}
	
	public static function ensurePathExistenceByDestination ($path = '')
	{
		$result = false;
		
		$path = XXX_Path_Local::getParentPath($path);
		
		return self::ensurePathExistence($path);
	}
		
	public static function setUmask ($permissions = '110')
	{
		return umask(octdec($permissions));
	}
		
	// Run periodically in long-running processes like daemons to avoid file system information being incorrect
	public static function clearInformationCache ()
	{
		return clearstatcache();	
	}
	
	////////////////////
	// File Stream
	////////////////////
		
		/*
		
		Access modes:			
			- r = read
			- w = write (Truncates the file to 0 length before writing)
			- a = write append
				t = translation mode
				b = binary mode
				*t or b should be at the end
			
			- rb: Read Binary
			- wb: Write Binary
			- ab: Write Append Binary		
			
		Whence modes:
			- SEEK_SET: Set position equal to offset bytes.
        	- SEEK_CUR: Set position to current location plus offset.
        	- SEEK_END: Set position to end-of-file plus offset.
			
		*/
		
		public static function fileStream_open ($path = '', $mode = 'write', $ensurePathExistence = true)
		{
			$mode = XXX_Default::toOption($mode, array('read', 'write', 'writeAppend'), 'write');
			
			if (($mode == 'write' || $mode == 'writeAppend') && $ensurePathExistence)
			{
				self::ensurePathExistenceByDestination($path);
			}
			
			$fileStream = fopen($path, $mode == 'read' ? 'rb' : ($mode == 'write' ? 'wb' : 'ab'));
			
			return $fileStream;
		}
		
			public static function fileStream_openForReading ($path = '')
			{
				return self::fileStream_open($path, 'read', false);
			}
					
			public static function fileStream_openForWriting ($path = '', $ensurePathExistence = true)
			{
				return self::fileStream_open($path, 'write', $ensurePathExistence);
			}
						
			public static function fileStream_openForAppendedWriting ($path = '', $ensurePathExistence = true)
			{
				return self::fileStream_open($path, 'writeAppend', $ensurePathExistence);
			}
		
		public static function fileStream_close ($fileStream)
		{
			return fclose($fileStream);
		}
				
		public static function fileStream_setReadOffset ($fileStream, $offset = 0)
		{
			$result = false;
			
			if (XXX_Type::isPositiveInteger($offset))
			{
				$result = fseek($fileStream, $offset, SEEK_SET);
			}
			
			return $result;
		}
		
		public static function fileStream_getReadOffset ($fileStream)
		{
			return ftell($fileStream);
		}
		
		public static function fileStream_writeChunk ($fileStream, $data = '', $bytesToWrite = 0)
		{
			return fwrite($fileStream, $data, $bytesToWrite);
		}
		
		public static function fileStream_writeLine ($fileStream, $data = '')
		{
			return fputs($fileStream, $data);
		}
		
		public static function fileStream_readChunk ($fileStream, $bytesToRead = 0)
		{
			return fread($fileStream, $bytesToRead);
		}
		
		public static function fileStream_readLine ($fileStream)
		{
			return fgets($fileStream);
		}
		
		public static function fileStream_hasReadReachedEnd ($fileStream)
		{
			return feof($fileStream);
		}
		
	////////////////////
	// File
	////////////////////
			
		////////////////////
		// Information
		////////////////////
		
		public static function getFileChecksum ($path = '', $checksumType = 'md5')
		{
			$result = false;
			
			$checksumType = XXX_Default::toOption($checksumType, array('md5', 'sha1'), 'md5');
			
			switch ($checksumType)
			{
				case 'sha1':
					$result = sha1_file($path);
					break;
				case 'md5':
				default:
					$result = md5_file($path);	
					break;
			}
			
			return $result;
		}
		
		public static function getFileSize ($path = '')
		{
			$result = filesize($path);
			
			return $result;
		}
		
		// Used for caching templates etc.
		public static function getFileModifiedTimestamp ($path = '')
		{
			$result = filemtime($path);
			
			return $result;
		}
		
		public static function getFileCreatedTimestamp ($path = '')
		{
			$result = filectime($path);
			
			return $result;
		}
		
		public static function getFileAccessedTimestamp ($path = '')
		{
			$result = fileatime($path);
			
			return $result;
		}
		
		public static function getFileMIMEType ($path = '')
		{
			$result = false;
						
			if (!$result && function_exists('mime_content_type'))
			{
				$result = mime_content_type($path);
			}
			
			if (!$result && function_exists('finfo_open') && self::$mimeMagicPath)
			{
				$fileInfoHandler = finfo_open(FILEINFO_MIME, self::$mimeMagicPath);
				$result = finfo_file($fileInfoHandler, $path);
				finfo_close($fileInfoHandler);
			}
			
			if (!$result)
			{
				$result = 'application/octet-stream';
			}
			
			$result = str_replace(' ', '', $result);
			$resultParts = explode(';', $result);
			
			$result = $resultParts[0];
					
			return $result;
		}
		
		public static function determineMostSpecificMIMEType (array $mimeTypes = array())
		{
			$result = 'application/octet-stream';
			
			foreach ($mimeTypes as $mimeType)
			{
				if ($mimeType != 'application/octet-stream')
				{
					$result = $mimeType;
					
					break;
				}
			}
			
			return $result;
		}
		
		public static function getFileExtension ($path = '')
		{
			$result = false;
			
			$identifier = XXX_Path_Local::getIdentifier($path);
			
			return XXX_String::getFileExtension($identifier);
		}
		
		public static function getFileName ($path = '')
		{
			$result = false;
			
			$identifier = XXX_Path_Local::getIdentifier($path);
			
			return XXX_String::getFileName($identifier);
		}
		
		public static function getFileInformation ($path = '')
		{
			$result = false;
			
			if (self::doesFileExist($path))
			{
				$identifier = XXX_Path_Local::getIdentifier($path);
				
				$result = array
				(
				 	
					'file' => $identifier,
					'name' => self::getFileName($path),
					'extension' => self::getFileExtension($path),
					'mimeType' => self::getFileMIMEType($path),
					'size' => self::getFileSize($path),
					'textEditable' => self::isFileContentTextEditable($path),
					'permissions' => self::getFilePermissions($path, true),
					'checksum' => self::getFileChecksum($path, 'md5')
				);
			}
			
			return $result;
		}
		
		public static function isUploadedFile ($temporaryFilePath = '')
		{
			return is_uploaded_file($temporaryFilePath);
		}
				
		public static function isFileContentTextEditable ($path = '')
		{
			$result = false;
			
			if (!$result)
			{
				$extension = self::getFileExtension($path);
				
				if ($extension)
				{
					if (XXX_Array::hasValue(array('log', 'txt', 'sh', 'bash', 'php', 'conf', 'ini', 'html', 'htm', 'tmp', 'css', 'js', 'xml', 'json', 'yaml'), $extension))
					{
						$result = true;
					}
				}
			}
			
			if (!$result)
			{			
				$mimeType = self::getFileMIMEType($path);
																
				if (XXX_String::beginsWith($mimeType, 'text'))
				{
					$result = true;
				}
			}
			
			if ($result)
			{
				$size = self::getFileSize($path);
				
				if ($size > XXX_PHP::$executionLimits['maximumMemorySize'])
				{
					$result = false;
				}
			}
			
			return $result;
		}
			
		////////////////////
		// Content
		////////////////////
			
			public static function createFile ($path = '')
			{
				return self::writeFileContent($path, '');
			}
			
			public static function getFileContent ($path = '')
			{
				$result = false;
				
				if (self::doesFileExist($path))
				{
					if (!$result && function_exists('file_get_contents'))
					{
						$result = file_get_contents($path);
					}
					
					if (!$result)
					{	
						$fileHandler = fopen($path, 'rb');
						
						$result = fread($fileHandler, self::getFileSize($path));
						
						fclose($fileHandler);
					}
				}
				
				return $result;
			}
					
			public static function getTailedFileContent ($path = '', $uniqueID = 0)
			{				
				$result = false;
				
				if (self::doesFileExist($path))
				{
					$fileCreatedTimestamp = self::getFileCreatedTimestamp($path);
					$fileModifiedTimestamp = self::getFileModifiedTimestamp($path);
					$fileSize = self::getFileSize($path);
					
					$lastLineRead = -1;
					$bytesRead = 0;
					
					$newContentAvailable = false;
					
					// convert path directorySeparators to _ so for windows it'll work as well
					$tempFilePath = XXX_Path_Local::extendPath('/tmp', 'application/getTailedFileContent/' . $uniqueID . '/' . $path);
					
					// Try using the tempFile checking for previous reads
						if (self::doesFileExist($tempFilePath))
						{
							$tempFileContent = self::getFileContent($tempFilePath);
							$tempFileContent = XXX_String_PHPON::decode($tempFileContent);
							
							$tempFileCreatedTimestamp = $tempFileContent['createdTimestamp'];
							$tempFileModifiedTimestamp = $tempFileContent['modifiedTimestamp'];
							$tempFileSize = $tempFileContent['size'];
							$tempFileLastLineRead = $tempFileContent['lastLineRead'];
							$tempFileBytesRead = $tempFileContent['bytesRead'];
							
							// Different file size or new created or has changed
							if ($fileSize > $tempFileSize || $fileCreatedTimestamp > $tempFileCreatedTimestamp || $fileModifiedTimestamp > $tempFileModifiedTimestamp)
							{
								$lastLineRead = $tempFileLastLineRead;
								$bytesRead = $tempFileBytesRead;
								
								$newContentAvailable = true;
							}
						}
						else
						{
							$newContentAvailable = true;
						}
					
						if ($newContentAvailable)
						{
							$result = '';
							
							// Read the lines since the last time
								$fileHandler = fopen($path, 'r');
								fseek($fileHandler, $bytesRead);
								
								if ($fileHandler)
								{
									$currentLine = $lastLineRead == -1 ? 0 : $lastLineRead;
									
									while (($line = fgets($fileHandler, 8192)) !== false)
									{
										if ($currentLine > $lastLineRead)
										{
									        $result .= $line;
									        
									        $bytesRead += XXX_String::getByteSize($line);
									        ++$lastLineRead;
								        }
								        
								        ++$currentLine;
								    }
								    
								    if (!feof($fileHandler))
								    {
								        // Unexpected failure, not end of file
										$result = false;
								    }
								    fclose($fileHandler);
								}
								
							
							// Try storing the tempFile
								$tempFileContent = array
								(
									'createdTimestamp' => $fileCreatedTimestamp,
									'modifiedTimestamp' => $fileModifiedTimestamp,
									'size' => $fileSize,
									'lastLineRead' => $lastLineRead,
									'bytesRead' => $bytesRead
								);
								
								$tempFileContent = XXX_String_PHPON::encode($tempFileContent);
								
								$saved = self::writeFileContent($tempFilePath, $tempFileContent);
								
								if (!$saved)
								{
									$result = false;
								}
						}
					
				}
				
				return $result;
			}
			
			public static function getLastLine ($path = '')
			{
				$result = false;
				
				if (self::doesFileExist($path))
				{
					$line = '';
					$fileHandler = fopen($path, 'r');
					$cursor = -1;
					
					fseek($fileHandler, $cursor, SEEK_END);
					$character = fgetc($fileHandler);
					
					// Trim trailing newLine characters of the file end
					while ($character === "\r" || $character === "\n")
					{
						fseek($fileHandler, $cursor--, SEEK_END);
						$character = fgetc($fileHandler);
					}
					
					// Read until the start of the file or first line separator
					while ($character !== false && $character !== "\r" && $character !== "\n")
					{
						$line = $character . $line;
						
						fseek($fileHandler, $cursor--, SEEK_END);						
						$character = fgetc($fileHandler);
					}
					
					fclose($fileHandler);
					
					$result = $line;
				}
				
				return $result;
			}
			
			public static function getFirstLine ($path = '')
			{
				$result = false;
				
				if (self::doesFileExist($path))
				{
					$line = '';
					$fileHandler = fopen($path, 'r');
					$cursor = 0;
					
					fseek($fileHandler, $cursor, SEEK_SET);
					$character = fgetc($fileHandler);
										
					// Read until the start of the file or first line separator
					while ($character !== false && $character !== "\r" && $character !== "\n")
					{
						$line .= $character;
						
						fseek($fileHandler, $cursor++, SEEK_SET);
						$character = fgetc($fileHandler);
					}
					
					fclose($fileHandler);
					
					$result = $line;
				}
				
				return $result;
			}
			
			public static function writeFileContent ($path = '', $content = '')
			{
				$result = false;
				
				self::ensurePathExistenceByDestination($path);
				
				if (!$result && function_exists('file_put_contents'))
				{
					$result = file_put_contents($path, $content);
				}
				
				if (!$result)
				{	
					$fileHandler = fopen($path, 'wb');
					
					if ($fileHandler)
					{
						fwrite($fileHandler, $content);
						fclose($fileHandler);
						
						$result = true;
					}			
				}
					
				return $result;
			}
			
			public static function appendFileContent ($path = '', $content = '')
			{
				$result = false;
				
				self::ensurePathExistenceByDestination($path);
				
				if (!$result && function_exists('file_put_contents'))
				{
					$result = file_put_contents($path, $content, FILE_APPEND);
				}
				
				if (!$result)
				{	
					$fileHandler = fopen($path, 'ab');
					
					if ($fileHandler)
					{
						fwrite($fileHandler, $content);
						fclose($fileHandler);
						
						$result = true;
					}
				}
				
				return $result;
			}
			
			public static function emptyFile ($path = '')
			{
				return self::writeFileContent($path, '');
			}
			
						
		////////////////////
		// Access
		////////////////////
		
			public static function doesFileExist ($path = '')
			{
				$result = file_exists($path) && is_file($path);
				
				return $result;
			}
			
			public static function isFileAccessible ($path = '')
			{
				return self::doesFileExist($path);
			}
		
		////////////////////
		// Tree
		////////////////////
		
			public static function renameFile ($path = '', $newPath = '', $overwrite = true)
			{
				$result = false;
				
				self::ensurePathExistenceByDestination($newPath);
				
				if (self::doesFileExist($path))
				{
					$newPathExists = self::doesFileExist($newPath);
					
					if (!$newPathExists || $overwrite)
					{
						$clear = true;
						
						if ($newPathExists)
						{
							if (!self::deleteFile($newPath))
							{
								$clear = false;
							}
						}
						
						if ($clear)
						{
							$result = rename($path, $newPath);
						}
					}
				}
				
				return $result;
			}
			
			public static function moveFile ($path = '', $newPath = '', $overwrite = true)
			{
				return self::renameFile($path, $newPath, $overwrite);
			}
			
			public static function moveUploadedFile ($temporaryFilePath = '', $newPath = '', $overwrite = true)
			{
				$result = false;
				
				if (self::isUploadedFile($temporaryFilePath))
				{
					$pathExists = self::ensurePathExistenceByDestination($newPath);
					
					if ($pathExists)
					{
						$newPathExists = self::doesFileExist($newPath);
						
						if ($newPathExists && $overwrite)
						{
							if (self::deleteFile($newPath))
							{
								$result = move_uploaded_file($temporaryFilePath, $newPath);
							}
						}
						else
						{
							$result = move_uploaded_file($temporaryFilePath, $newPath);
						}
					}
				}			
						
				return $result;
			}
			
			public static function copyFile ($path = '', $newPath = '', $overwrite = true)
			{
				$result = false;
				
				$newPathExists = self::ensurePathExistenceByDestination($newPath);
				
				if ($newPathExists)
				{
					if (self::doesFileExist($path))
					{
						$newPathExists = self::doesFileExist($newPath);
						
						if (!$newPathExists || $overwrite)
						{
							$clear = true;
							
							if ($newPathExists)
							{
								if (!self::deleteFile($newPath))
								{
									$clear = false;
								}
							}
							
							if ($clear)
							{
								$result = copy($path, $newPath);
							}
						}
					}
				}
				
				return $result;
			}
			
			public static function deleteFile ($path = '')
			{
				$result = false;
				
				if (self::doesFileExist($path))
				{
					$result = unlink($path);
				}
				
				return $result;
			}
			
			public static function deleteUploadedFile ($absolutePathToTemporaryFile = '')
			{
				return self::deleteFile($absolutePathToTemporaryFile);
			}
		
		////////////////////
		// Permissions
		////////////////////
			
			// Attempts to set permissions, only the owner user can set the permissions
			public static function setFilePermissions ($path = '', $permissions = '660')
			{
				$result = chmod($path, octdec($permissions));
				
				return $result;
			}
			
			public static function getFilePermissions ($path = '', $parsed = false)
			{
				$filePermissions = fileperms($path);
				
				if ($parsed)
				{
					$result = self::parseFilePermissions($filePermissions);				
				}
				else
				{
					$result = $filePermissions;
				}
				
				return $result;
			}
			
			public static function parseFilePermissions ($filePermissions)
			{
				$result = array
				(
					'raw' => $filePermissions,
					'string' => '',
					'stringSimple' => '',
					'octalString' => '0'
				);
				
				if (($filePermissions & 0xC000) == 0xC000)
				{
				    // Socket
				    $result['identifierType'] = 's';
				}
				else if (($filePermissions & 0xA000) == 0xA000)
				{
				    // Symbolic Link
				    $result['identifierType'] = 'l';
				}
				elseif (($filePermissions & 0x8000) == 0x8000)
				{
				    // Regular (File)
				    $result['identifierType'] = '-';
				}
				elseif (($filePermissions & 0x6000) == 0x6000)
				{
				    // Block special
				    $result['identifierType'] = 'b';
				}
				elseif (($filePermissions & 0x4000) == 0x4000)
				{
				    // Directory
				    $result['identifierType'] = 'd';
				}
				elseif (($filePermissions & 0x2000) == 0x2000)
				{
				    // Character special
				    $result['identifierType'] = 'c';
				}
				elseif (($filePermissions & 0x1000) == 0x1000)
				{
				    // FIFO pipe
				    $result['identifierType'] = 'p';
				}
				else
				{
				    // Unknown
				    $result['identifierType'] = 'u';
				}
				
				$result['type'] = $result['identifierType'] != 'd' ? 'file' : 'directory';
				
				$result['file'] = $result['identifierType'] != 'd';
				$result['directory'] = $result['identifierType'] == 'd';
				
				// User
					
					$result['user'] = array
					(
						'read' => false,
						'write' => false,
						'execute' => false,
						'setUID' => false,
						'string' => '',
						'stringSimple' => '',
						'octalString' => '0'
						
					);
					
					if ($filePermissions & 0x0100)
					{
						$result['user']['read'] = true;
						$result['user']['string'] .= 'r';
						$result['user']['stringSimple'] .= 'r';
					}
					else
					{
						$result['user']['string'] .= '-';
						$result['user']['stringSimple'] .= '-';
					}
					
					
					if ($filePermissions & 0x0080)
					{
						$result['user']['write'] = true;
						$result['user']['string'] .= 'w';
						$result['user']['stringSimple'] .= 'w';
					}
					else
					{
						$result['user']['string'] .= '-';
						$result['user']['stringSimple'] .= '-';
					}
					
					if ($filePermissions & 0x0040)
					{
						if ($filePermissions & 0x0800)
						{
							$result['user']['execute'] = true;
							$result['user']['setUID'] = true;
							$result['user']['string'] .= 's';
						}
						else
						{					
							$result['user']['execute'] = true;
							$result['user']['string'] .= 'x';
						}
						
						$result['user']['stringSimple'] .= 'x';
					}
					else
					{
						if ($filePermissions & 0x0800)
						{
							$result['user']['setUID'] = true;
							$result['user']['string'] .= 'S';
						}
						else
						{					
							$result['user']['string'] .= '-';
						}
						
						$result['user']['stringSimple'] .= '-';
					}
					
					if ($result['user']['read'] && $result['user']['write'] && $result['user']['execute'])
					{
						$result['user']['octalString'] = '7';
					}
					else if ($result['user']['read'] && $result['user']['write'])
					{
						$result['user']['octalString'] = '6';
					}
					else if ($result['user']['read'] && $result['user']['execute'])
					{
						$result['user']['octalString'] = '5';
					}
					else if ($result['user']['read'])
					{
						$result['user']['octalString'] = '4';
					}
					else if ($result['user']['write'] && $result['user']['execute'])
					{
						$result['user']['octalString'] = '3';
					}
					else if ($result['user']['write'])
					{
						$result['user']['octalString'] = '2';
					}
					else if ($result['user']['execute'])
					{
						$result['user']['octalString'] = '1';
					}
					else
					{
						$result['user']['octalString'] = '0';
					}
				
				// Group
					
					$result['group'] = array
					(
						'read' => false,
						'write' => false,
						'execute' => false,
						'setGID' => false,
						'string' => '',
						'stringSimple' => '',
						'octalString' => '0'
						
					);
					
					if ($filePermissions & 0x0020)
					{
						$result['group']['read'] = true;
						$result['group']['string'] .= 'r';
						$result['group']['stringSimple'] .= 'r';
					}
					else
					{
						$result['group']['string'] .= '-';
						$result['group']['stringSimple'] .= '-';
					}
					
					
					if ($filePermissions & 0x0010)
					{
						$result['group']['write'] = true;
						$result['group']['string'] .= 'w';
						$result['group']['stringSimple'] .= 'w';
					}
					else
					{
						$result['group']['string'] .= '-';
						$result['group']['stringSimple'] .= '-';
					}
					
					if ($filePermissions & 0x0008)
					{
						if ($filePermissions & 0x0400)
						{
							$result['group']['execute'] = true;
							$result['group']['setGID'] = true;
							$result['group']['string'] .= 's';
						}
						else
						{					
							$result['group']['execute'] = true;
							$result['group']['string'] .= 'x';
						}
						
						$result['group']['stringSimple'] .= 'x';
					}
					else
					{
						if ($filePermissions & 0x0400)
						{
							$result['group']['setGID'] = true;
							$result['group']['string'] .= 'S';
						}
						else
						{					
							$result['group']['string'] .= '-';
						}
						
						$result['group']['stringSimple'] .= '-';
					}
					
					if ($result['group']['read'] && $result['group']['write'] && $result['group']['execute'])
					{
						$result['group']['octalString'] = '7';
					}
					else if ($result['group']['read'] && $result['group']['write'])
					{
						$result['group']['octalString'] = '6';
					}
					else if ($result['group']['read'] && $result['group']['execute'])
					{
						$result['group']['octalString'] = '5';
					}
					else if ($result['group']['read'])
					{
						$result['group']['octalString'] = '4';
					}
					else if ($result['group']['write'] && $result['group']['execute'])
					{
						$result['group']['octalString'] = '3';
					}
					else if ($result['group']['write'])
					{
						$result['group']['octalString'] = '2';
					}
					else if ($result['group']['execute'])
					{
						$result['group']['octalString'] = '1';
					}
					else
					{
						$result['group']['octalString'] = '0';
					}
				
				// Other
					
					$result['other'] = array
					(
						'read' => false,
						'write' => false,
						'execute' => false,
						'stickyBit' => false,
						'string' => '',
						'stringSimple' => '',
						'octalString' => '0'
						
					);
					
					if ($filePermissions & 0x0004)
					{
						$result['other']['read'] = true;
						$result['other']['string'] .= 'r';
						$result['other']['stringSimple'] .= 'r';
					}
					else
					{
						$result['other']['string'] .= '-';
						$result['other']['stringSimple'] .= '-';
					}
					
					
					if ($filePermissions & 0x0002)
					{
						$result['other']['write'] = true;
						$result['other']['string'] .= 'w';
						$result['other']['stringSimple'] .= 'w';
					}
					else
					{
						$result['other']['string'] .= '-';
						$result['other']['stringSimple'] .= '-';
					}
					
					if ($filePermissions & 0x0001)
					{
						if ($filePermissions & 0x0200)
						{
							$result['other']['execute'] = true;
							$result['other']['stickyBit'] = true;
							$result['other']['string'] .= 't';
						}
						else
						{					
							$result['other']['execute'] = true;
							$result['other']['string'] .= 'x';
						}
						
						$result['other']['stringSimple'] .= 'x';
					}
					else
					{
						if ($filePermissions & 0x0200)
						{
							$result['other']['execute'] = true;
							$result['other']['stickyBit'] = true;
							$result['other']['string'] .= 'T';
						}
						else
						{					
							$result['other']['string'] .= '-';
						}
						
						$result['other']['stringSimple'] .= '-';
					}
					
					if ($result['other']['read'] && $result['other']['write'] && $result['other']['execute'])
					{
						$result['other']['octalString'] = '7';
					}
					else if ($result['other']['read'] && $result['other']['write'])
					{
						$result['other']['octalString'] = '6';
					}
					else if ($result['other']['read'] && $result['other']['execute'])
					{
						$result['other']['octalString'] = '5';
					}
					else if ($result['other']['read'])
					{
						$result['other']['octalString'] = '4';
					}
					else if ($result['other']['write'] && $result['other']['execute'])
					{
						$result['other']['octalString'] = '3';
					}
					else if ($result['other']['write'])
					{
						$result['other']['octalString'] = '2';
					}
					else if ($result['other']['execute'])
					{
						$result['other']['octalString'] = '1';
					}
					else
					{
						$result['other']['octalString'] = '0';
					}
				
				$result['string'] = $result['identifierType'] . $result['user']['string'] . $result['group']['string'] . $result['other']['string'];
				$result['stringSimple'] = $result['user']['stringSimple'] . $result['group']['stringSimple'] . $result['other']['stringSimple'];
				$result['octalString'] = $result['user']['octalString'] . $result['group']['octalString'] . $result['other']['octalString'];
				$result['octalInteger'] = octdec($result['octalString']);
				
				return $result;
			}
			
			// User
			
				// Attempts, only the super user can change the owner.
				public static function setFileUser ($path = '', $userNameOrNumber = false)
				{
					$result = chown($path, $userNameOrNumber);
					
					return $result;
				}
				
				public static function getFileUser ($path = '')
				{
					$result = false;
					
					if (function_exists('posix_getpwuid'))
					{					
						$userID = fileowner($path);
						$userName = posix_getpwuid($userID);
						$userName = $userName['name'];
						
						if ($userID !== false)
						{
							$result = array
							(
								'ID' => $userID,
								'name' => $userName
							);
						}
					}
					
					return $result;
				}
			
			// Group
			
				// Attempts, only the super user can change the group.
				public static function setFileGroup ($path = '', $ownerNameOrNumber = false)
				{
					$result = chgrp($path, $ownerNameOrNumber);
					
					return $result;
				}
				
				public static function getFileGroup ($path = '')
				{
					$result = false;
					
					if (function_exists('posix_getgrgid'))
					{
						$groupID = filegroup($path);
						$groupName = posix_getgrgid($groupID);
						$groupName = $groupName['name'];
						
						if ($userID !== false)
						{
							$result = array
							(
								'ID' => $groupID,
								'name' => $groupName
							);
						}
					}
					
					return $result;
				}
			
			public static function getFileOwner ($path = '')
			{
				$result = false;
				
				$result = array
				(
					'user' => self::getFileUser($path),
					'group' => self::getFileGroup($path)
				);
				
				return $result;
			}
			
			public static function getFileAccessType ($path = '')
			{
				$result = 'other';
				
				$fileOwner = self::getFileOwner($path);
				$processOwner = XXX_PHP::$processOwner;
				
				if ($result == 'other')
				{
					if ($processOwner['user']['name'] == 'root')
					{
						$result = 'root';
					}
				}
				
				if ($result == 'other')
				{
					if ($fileOwner)
					{
						
						if ($fileOwner['user']['ID'] == $processOwner['user']['ID'])
						{
							$result = 'user';
						}
						else if ($fileOwner['group']['ID'] == $processOwner['group']['ID'])
						{
							$result = 'group';
						}
						else if ($fileOwner['user']['name'] != 'root' && $fileOwner['group']['name'] != 'root')
						{
							$groupInformation = posix_getgrgid($fileOwner['group']['ID']);
							
							if ($groupInformation)
							{
								if (XXX_Array::hasValue($groupInformation['members'], $processOwner['user']['name']))
								{
									$result = 'group';
								}
							}
						}
					}
				}
				
				return $result;
			}
			
			
			public static function isFileContentReadable ($path = '')
			{
				return is_readable($path);
			}
			
			public static function makeFileContentReadable ($path = '')
			{
				$permissions = self::getFilePermissions($path, true);
				
				$user = 0;
			
				$user += 4;
				if ($permissions['user']['write'])
				{
					$user += 2;
				}		
				if ($permissions['user']['execute'])
				{
					$user += 1;
				}
				
				$group = 0;
				
				$group += 4;
				if ($permissions['group']['write'])
				{
					$group += 2;
				}		
				if ($permissions['group']['execute'])
				{
					$group += 1;
				}
				
				$other = 0;
				
				$other += 4;
				if ($permissions['other']['write'])
				{
					$other += 2;
				}
				if ($permissions['other']['execute'])
				{
					$other += 1;
				}
				
				$permissions = $user . $group . $other;
				
				return self::setFilePermissions($path, $permissions);
			}
			
			public static function isFileContentWritable ($path = '')
			{
				return is_writable($path);
			}
			
			public static function makeFileContentWritable ($path = '')
			{
				$permissions = self::getFilePermissions($path, true);
				
				$user = 0;
			
				if ($permissions['user']['read'])
				{
					$user += 4;
				}			
				$user += 2;
				if ($permissions['user']['execute'])
				{
					$user += 1;
				}
				
				$group = 0;
				
				if ($permissions['group']['read'])
				{
					$group += 4;
				}			
				$group += 2;
				if ($permissions['group']['execute'])
				{
					$group += 1;
				}
				
				$other = 0;
				
				if ($permissions['other']['read'])
				{
					$other += 4;
				}
				$other += 2;
				if ($permissions['other']['execute'])
				{
					$other += 1;
				}
				
				$permissions = $user . $group . $other;
				
				return self::setFilePermissions($path, $permissions);
			}
			
			public static function isFileExecutable ($path = '')
			{
				return is_executable($path);
			}
						
			public static function makeFileExecutable ($path = '')
			{
				$permissions = self::getFilePermissions($path, true);
				
				$user = 0;
			
				if ($permissions['user']['read'])
				{
					$user += 4;
				}			
				if ($permissions['user']['write'])
				{
					$user += 2;
				}		
				$user += 1;
				
				$group = 0;
				
				if ($permissions['group']['read'])
				{
					$group += 4;
				}			
				if ($permissions['group']['write'])
				{
					$group += 2;
				}		
				$group += 1;
				
				$other = 0;
				
				if ($permissions['other']['read'])
				{
					$other += 4;
				}
				if ($permissions['other']['write'])
				{
					$other += 2;
				}
				$other += 1;
				
				$permissions = $user . $group . $other;
				
				return self::setFilePermissions($path, $permissions);
			}
				
	////////////////////
	// Directory
	////////////////////
	
		////////////////////
		// Information
		////////////////////
		
		public static function getDirectoryStatistics ($path = '', $recursive = false)
		{
			$result = false;
			
			$size = 0;
			$directories = 0;
			$files = 0;
			
			if (self::doesDirectoryExist($path))
			{
				$content = self::getDirectoryContent($path, false);		
						
				if (XXX_Array::getFirstLevelItemTotal($content['directories']))
				{
					foreach ($content['directories'] as $tempDirectory)
					{
						if ($recursive)
						{				
							$tempResult = self::getDirectoryStatistics($tempDirectory['path'], $recursive);
							
							$size += $tempResult['size'];				
							$directories += $tempResult['directories'];
							$files += $tempResult['files'];
						}
						++$directories;
					}
				}
				
				if (XXX_Array::getFirstLevelItemTotal($content['files']))
				{
					foreach ($content['files'] as $tempFile)
					{				
						$size += self::getFileSize($tempFile['path']);
						
						++$files;
					}
				}
				
				$result = array
				(
					'size' => $size,
					'directories' => $directories,
					'files' => $files
				);
			}
			
			return $result;
		}
		
		////////////////////
		// Content
		////////////////////
		
		public static function customSortDirectoryNames ($a, $b)
		{
			return $a['directory'] > $b['directory'];
		}
		
		
		public static function customSortFileNames ($a, $b)
		{
			return $a['file'] > $b['file'];
		}
		
		public static function getDirectoryContent ($path = '', $recursive = false)
		{
			$result = false;
			
			if (self::doesDirectoryExist($path))
			{
				if (self::isDirectoryContentReadable($path))
				{
					$result = array
					(
						'directories' => array(),
						'files' => array()
					);
					$directoryHandler = opendir($path);
								
					while (($item = readdir($directoryHandler)) !== false)
					{
						// Make sure the file pointer is an actual item instead of the current directory or the parent directory
						if (self::isNonSystemIdentifier($item))
						{
							$tempItemPath = XXX_Path_Local::extendPath($path, $item);
							
							// Directory
							if (self::doesDirectoryExist($tempItemPath))
							{
								$tempDirectory = array();
								
								$tempDirectory['path'] = $tempItemPath;
								$tempDirectory['directory'] = $item;
								
								if ($recursive)
								{
									$tempDirectoryContent = self::getDirectoryContent($tempItemPath, $recursive);
									
									if ($tempDirectoryContent)
									{
										$tempDirectory['directories'] = $tempDirectoryContent['directories'];
										$tempDirectory['files'] = $tempDirectoryContent['files'];
									}
								}							
								
								$result['directories'][] = $tempDirectory;
							}
							// File
							else
							{
								$tempFile = array();
								
								$tempFile['path'] = $tempItemPath;							
								$tempFile['file'] = $item;
								
								$result['files'][] = $tempFile;
							}
						}
					}
					
					usort($result['files'], 'self::customSortFileNames');
					usort($result['directories'], 'self::customSortDirectoryNames');
					
					closedir($directoryHandler);
				}
			}
			
			return $result;
		}
		
		public static function getDirectoryContentForFileBrowser ($path = '')
		{
			$result = false;
			
			$directoryContent = self::getDirectoryContent($path, false);
												
			if ($directoryContent)
			{
				$result = array();
				
				if (XXX_Array::getFirstLevelItemTotal($directoryContent['directories']))
				{
					foreach ($directoryContent['directories'] as $directory)
					{
						$tempDirectory = array
						(
							'path' => $directory['path'],
							'directory' => $directory['directory'],
							'modifiedTimestamp' => self::getFileModifiedTimestamp($directory['path']),
							'owner' => self::getDirectoryOwner($directory['path']),
							'permissions' => self::getDirectoryPermissions($directory['path'], true)
						);
						
						$result['directories'][] = $tempDirectory;
					}
				}
				
				if (XXX_Array::getFirstLevelItemTotal($directoryContent['files']))
				{
					foreach ($directoryContent['files'] as $file)
					{
						$tempFile = array
						(
							'path' => $file['path'],
							'file' => $file['file'],
							'extension' => XXX_String::getFileExtension($file['file']),
							'modifiedTimestamp' => self::getFileModifiedTimestamp($file['path']),
							'size' => self::getFileSize($file['path']),
							'mimeType' => self::getFileMIMEType($file['path']),
							'textEditable' => self::isFileContentTextEditable($file['path']),
							'owner' => self::getFileOwner($file['path']),
							'permissions' => self::getFilePermissions($file['path'], true)
						);
						
						$result['files'][] = $tempFile;
					}
				}
			}
			
			return $result;
		}
		
		
		public static function getFilesOlderThanInDirectory ($path = '', $olderThan = false)
		{
			$result = false;
			
			if ($olderThan === false)
			{
				$olderThan = XXX_TimestampHelpers::getCurrentTimestamp();
			}
			
			$directoryContent = self::getDirectoryContent($path, false);
												
			if ($directoryContent)
			{
				$result = array();
				
				if (XXX_Array::getFirstLevelItemTotal($directoryContent['files']))
				{
					foreach ($directoryContent['files'] as $file)
					{
						$tempFileCreatedTimestamp = self::getFileCreatedTimestamp($file['path']);
						
						if ($tempFileCreatedTimestamp < $olderThan)
						{
							$result[] = array
							(
								'path' => $file['path'],
								'file' => $file['file'],
								'createdTimestamp' => $tempFileCreatedTimestamp
							);
						}
					}
				}
			}
			
			return $result;
		}
		
		public static function getNotRecentlyAccessedFilesInDirectory ($path = '', $maximumInterval = 0)
		{
			$result = false;
			
			$now = XXX_TimestampHelpers::getCurrentTimestamp();
			
			$directoryContent = self::getDirectoryContent($path, false);
												
			if ($directoryContent)
			{
				$result = array();
				
				if (XXX_Array::getFirstLevelItemTotal($directoryContent['files']))
				{
					foreach ($directoryContent['files'] as $file)
					{
						$tempFileAccessedTimestamp = self::getFileAccessedTimestamp($file['path']);
						
						if (($now - $tempFileAccessedTimestamp) > $maximumInterval)
						{
							$result[] = array
							(
								'path' => $file['path'],
								'file' => $file['file'],
								'accessedTimestamp' => $tempFileAccessedTimestamp
							);
						}
					}
				}
			}
			
			return $result;
		}
		
		// TODO log rotation function
		
		////////////////////
		// Access
		////////////////////
		
			public static function doesDirectoryExist ($path = '')
			{
				$result = file_exists($path) && is_dir($path);
				
				return $result;
			}
			
			public static function isDirectoryAccessible ($path = '')
			{
				return self::doesDirectoryExist($path);
			}
			
		////////////////////
		// Tree
		////////////////////
		
			public static function createDirectory ($path = '')
			{
				$result = false;
				
				if (!self::doesDirectoryExist($path) && self::isNonSystemIdentifier($path))
				{
					$result = mkdir($path, self::$settings['defaultPermissions']['directory']);
					
					if ($result)
					{
						self::setDirectoryPermissions($path, self::$settings['defaultPermissions']['directory']);
					}
					else
					{
						trigger_error('Failed to create directory: "' . $path . '"', E_USER_ERROR);
					}
				}
				else
				{
					$result = true;
				}
				
				return $result;
			}
			
			public static function renameDirectory ($path = '', $newPath = '')
			{
				$result = false;
				
				if (self::doesDirectoryExist($path) && !self::doesDirectoryExist($newPath))
				{		
					if (self::ensurePathExistenceByDestination($newPath))
					{
						$result = rename($path, $newPath);
					}
				}
				
				return $result;
			}
			
			public static function moveDirectory ($path = '', $newPath = '')
			{
				return self::renameDirectory($path, $newPath);
			}
			
			public static function copyDirectory ($path = '', $newPath = '', $overwrite = true)
			{
				$result = false;
				
				if (self::doesDirectoryExist($path))
				{
					$directoryContent = self::getDirectoryContent($path, false);
					
					if (self::ensurePathExistence($newPath))
					{
						if ($directoryContent !== false)
						{
							$result = true;
							
							if (XXX_Array::getFirstLevelItemTotal($directoryContent['directories']))
							{
								foreach ($directoryContent['directories'] as $directory)
								{							
									if (!self::copyDirectory($directory['path'], XXX_Path_Local::extendPath($newPath, $directory['directory']), $overwrite))
									{
										$result = false;
									}
								}
							}
							
							if (XXX_Array::getFirstLevelItemTotal($directoryContent['files']))
							{
								foreach ($directoryContent['files'] as $file)
								{								
									if (!self::copyFile($file['path'], XXX_Path_Local::extendPath($newPath, $file['file']), $overwrite))
									{
										$result = false;
									}
								}
							}
						}
					}
				}
				
				return $result;
			}
			
			public static function emptyDirectory ($path = '')
			{
				$result = false;
				
				if (self::doesDirectoryExist($path))
				{
					$directoryContent = self::getDirectoryContent($path, false);
					
					if ($directoryContent !== false)
					{
						$result = true;
						
						if (XXX_Array::getFirstLevelItemTotal($directoryContent['directories']))
						{
							foreach ($directoryContent['directories'] as $directory)
							{						
								if (!self::deleteDirectory($directory['path']))
								{
									$result = false;
								}
							}
						}
						
						if (XXX_Array::getFirstLevelItemTotal($directoryContent['files']))
						{
							foreach ($directoryContent['files'] as $file)
							{
								if (!self::deleteFile($file['path']))
								{
									$result = false;
								}
							}
						}
					}
				}
				
				return $result;
			}
			
			// TODO clean directory of files not accessed in x time, created x time ago etc.
			
			public static function deleteDirectory ($path = '')
			{
				$result = false;
				
				if (self::doesDirectoryExist($path))
				{
					$result = self::emptyDirectory($path);
					
					if ($result)
					{
						$result = rmdir($path);
					}
				}
				
				return $result;
			}
			
				
		////////////////////
		// Permissions
		////////////////////
			
			public static function getDirectoryPermissions ($path = '', $parse = false)
			{
				return self::getFilePermissions($path, $parse);
			}
			
			public static function setDirectoryPermissions ($path = '', $permissions = '770', $recursive = false)
			{
				$result = self::setFilePermissions($path, $permissions);
				
				if ($recursive)
				{
					$directoryContent = self::getDirectoryContent($path, false);
												
					if ($directoryContent)
					{
						if (XXX_Array::getFirstLevelItemTotal($directoryContent['directories']))
						{
							foreach ($directoryContent['directories'] as $directory)
							{
								$result = self::setDirectoryPermissions($directory['path'], $permissions, true);
							}							
						}
					}
				}
				
				return $result;
			}
				
				public static function setFilePermissionsInDirectory ($path = '', $permissions = '770', $recursive = false)
				{
					$result = false;
					
					$directoryContent = self::getDirectoryContent($path, false);
												
					if ($directoryContent)
					{
						if ($recursive)
						{
							if (XXX_Array::getFirstLevelItemTotal($directoryContent['directories']))
							{
								foreach ($directoryContent['directories'] as $directory)
								{
									$result = self::setFilePermissionsInDirectory($directory['path'], $permissions, true);
								}							
							}
						}
						
						if (XXX_Array::getFirstLevelItemTotal($directoryContent['files']))
						{
							foreach ($directoryContent['files'] as $file)
							{
								$result = self::setFilePermissions($file['path'], $permissions);
							}							
						}
					}
					
					return $result;
				}
			
			public static function getDirectoryAccessType ($path = '')
			{
				return self::getFileAccessType($path);
			}
			
			public static function getDirectoryUser ($path = '')
			{
				return self::getFileUser($path);
			}
			
			public static function setDirectoryUser ($path = '', $userNameOrNumber = false, $recursive = false)
			{
				$result = self::setFileUser($path, $userNameOrNumber);
				
				if ($recursive)
				{
					$directoryContent = self::getDirectoryContent($path, false);
												
					if ($directoryContent)
					{
						if (XXX_Array::getFirstLevelItemTotal($directoryContent['directories']))
						{
							foreach ($directoryContent['directories'] as $directory)
							{
								$result = self::setDirectoryUser($directory['path'], $userNameOrNumber, true);
							}							
						}
					}
				}
				
				return $result;
			}
				
				public static function setFileUserInDirectory ($path = '', $userNameOrNumber = false, $recursive = false)
				{
					$result = false;
							
					$directoryContent = self::getDirectoryContent($path, false);
												
					if ($directoryContent)
					{
						if ($recursive)
						{
							if (XXX_Array::getFirstLevelItemTotal($directoryContent['directories']))
							{
								foreach ($directoryContent['directories'] as $directory)
								{
									$result = self::setFileUserInDirectory($directory['path'], $userNameOrNumber, true);
								}							
							}
						}
						
						if (XXX_Array::getFirstLevelItemTotal($directoryContent['files']))
						{
							foreach ($directoryContent['files'] as $file)
							{
								$result = self::setFileUser($file['path'], $userNameOrNumber);
							}							
						}
					}
					
					return $result;
				}
			
			public static function getDirectoryGroup ($path = '')
			{
				return self::getFileGroup($path);
			}
			
			public static function setDirectoryGroup ($path = '', $groupNameOrNumber = false, $recursive = false)
			{
				$result = self::setFileGroup($path, $groupNameOrNumber);
				
				if ($recursive)
				{
					$directoryContent = self::getDirectoryContent($path, false);
					
					if ($directoryContent)
					{
						if (XXX_Array::getFirstLevelItemTotal($directoryContent['directories']))
						{
							foreach ($directoryContent['directories'] as $directory)
							{
								$result = self::setDirectoryGroup($directory['path'], $groupNameOrNumber, true);
							}							
						}
					}
				}
				
				return $result;
			}
				
				public static function setFileGroupInDirectory ($path = '', $groupNameOrNumber = false, $recursive = false)
				{
					$result = false;
							
					$directoryContent = self::getDirectoryContent($path, false);
												
					if ($directoryContent)
					{
						if ($recursive)
						{
							if (XXX_Array::getFirstLevelItemTotal($directoryContent['directories']))
							{
								foreach ($directoryContent['directories'] as $directory)
								{
									$result = self::setFileGroupInDirectory($directory['path'], $groupNameOrNumber, true);
								}
							}
						}
						
						if (XXX_Array::getFirstLevelItemTotal($directoryContent['files']))
						{
							foreach ($directoryContent['files'] as $file)
							{
								$result = self::setFileGroup($file['path'], $groupNameOrNumber);
							}							
						}
					}
					
					return $result;
				}
				
			public static function setDirectoryOwnerAdvanced ($path = '', $userNameOrNumber = '', $groupNameOrNumber = '', $recursive = false, $applyToFiles = false)
			{
				self::setDirectoryUser($path, $userNameOrNumber, $recursive);
				self::setDirectoryGroup($path, $groupNameOrNumber, $recursive);
				
				if ($applyToFiles)
				{
					self::setFileUserInDirectory($path, $userNameOrNumber, $recursive);
					self::setFileGroupInDirectory($path, $groupNameOrNumber, $recursive);
				}
			}
			
			public static function getDirectoryOwner ($path = '')
			{
				return self::getFileOwner($path);
			}
		
			public static function isDirectoryContentReadable ($path = '')
			{
				return is_readable($path);
			}
			
			public static function makeDirectoryContentReadable ($path = '', $recursive = false)
			{
				$permissions = self::getDirectoryPermissions($path, true);
				
				$user = 0;
			
				$user += 4;
				if ($permissions['user']['write'])
				{
					$user += 2;
				}		
				if ($permissions['user']['execute'])
				{
					$user += 1;
				}
				
				$group = 0;
				
				$group += 4;
				if ($permissions['group']['write'])
				{
					$group += 2;
				}		
				if ($permissions['group']['execute'])
				{
					$group += 1;
				}
				
				$other = 0;
				
				$other += 4;
				if ($permissions['other']['write'])
				{
					$other += 2;
				}
				if ($permissions['other']['execute'])
				{
					$other += 1;
				}
				
				$permissions = $user . $group . $other;
				
				return self::setDirectoryPermissions($path, $permissions, $recursive);
			}
			
			public static function isDirectoryContentWritable ($path = '')
			{
				return is_writable($path);
			}
			
			public static function makeDirectoryContentWritable ($path = '', $recursive = false)
			{
				$permissions = self::getDirectoryPermissions($path, true);
				
				$user = 0;
			
				if ($permissions['user']['read'])
				{
					$user += 4;
				}			
				$user += 2;
				if ($permissions['user']['execute'])
				{
					$user += 1;
				}
				
				$group = 0;
				
				if ($permissions['group']['read'])
				{
					$group += 4;
				}			
				$group += 2;
				if ($permissions['group']['execute'])
				{
					$group += 1;
				}
				
				$other = 0;
				
				if ($permissions['other']['read'])
				{
					$other += 4;
				}
				$other += 2;
				if ($permissions['other']['execute'])
				{
					$other += 1;
				}
				
				$permissions = $user . $group . $other;
				
				return self::setDirectoryPermissions($path, $permissions, $recursive);
			}
			
			public static function isDirectoryContentAccessible ($path = '')
			{
				return is_executable($path);
			}
			
			public static function makeDirectoryContentAccessible ($path = '', $recursive = false)
			{
				$permissions = self::getDirectoryPermissions($path, true);
				
				$user = 0;
			
				if ($permissions['user']['read'])
				{
					$user += 4;
				}			
				if ($permissions['user']['write'])
				{
					$user += 2;
				}		
				$user += 1;
				
				$group = 0;
				
				if ($permissions['group']['read'])
				{
					$group += 4;
				}			
				if ($permissions['group']['write'])
				{
					$group += 2;
				}		
				$group += 1;
				
				$other = 0;
				
				if ($permissions['other']['read'])
				{
					$other += 4;
				}
				if ($permissions['other']['write'])
				{
					$other += 2;
				}
				$other += 1;
				
				$permissions = $user . $group . $other;
				
				return self::setDirectoryPermissions($path, $permissions, $recursive);
			}
		
	
	public static function purgeDirectoryWithTimestampPartsForRetention ($path = '', $days = 7)
	{
		$now = XXX_TimestampHelpers::getCurrentTimestamp();
		
		$selectedSearchQuery_resultsDirectoryContent = XXX_FileSystem_Local::getDirectoryContent($path);
		
		foreach ($selectedSearchQuery_resultsDirectoryContent['directories'] as $yearDirectory)
		{
			$yearDirectoryContent = XXX_FileSystem_Local::getDirectoryContent($yearDirectory['path']);
			
			foreach ($yearDirectoryContent['directories'] as $monthDirectory)
			{
				$monthDirectoryContent = XXX_FileSystem_Local::getDirectoryContent($monthDirectory['path']);
				
				foreach ($monthDirectoryContent['directories'] as $dateDirectory)
				{
					$dateDirectoryContent = XXX_FileSystem_Local::getDirectoryContent($dateDirectory['path']);
					
					$year = XXX_Type::makeInteger($yearDirectory['directory']);
					$month = XXX_Type::makeInteger($monthDirectory['directory']);
					$date = XXX_Type::makeInteger($dateDirectory['directory']);
					
					$directoryTimestamp = new XXX_Timestamp(array('year' => $year, 'month' => $month, 'date' => $date));
					$directoryTimestamp = $directoryTimestamp->get();
					
					if ($now - $directoryTimestamp > $days * 86400)
					{
						self::deleteDirectory($dateDirectory['path']);
					}
				}
			}
		}
	}
	
	public static function correctOwnerAndPermissions ()
	{
		// Owner
			
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application', 'apache', 'intermediateApplication');		
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application/data', 'apache', 'intermediateApplication');
			
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application/data/backUps', 'apache', 'intermediateApplication', true, true);
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application/data/userFiles', 'apache', 'intermediateApplication', true, true);
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application/data/quarantinedFiles', 'root', 'intermediateApplication', true, true);
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application/data/ftpImportExport', 'ftpImportExport', 'intermediateApplication', true, true);
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application/data/mySQL', 'mysql', 'mysql', true, true);
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application/data/logs/application', 'apache', 'intermediateApplication', true, true);
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application/data/logs/mySQL', 'mysql', 'mysql', true, true);
			
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application/source', 'apache', 'intermediateApplication');
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application/source/apache', 'root', 'root');
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application/source/documentation', 'apache', 'intermediateApplication', true, true);
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application/source/dynamic', 'apache', 'intermediateApplication', true, true);
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application/source/static', 'apache', 'intermediateApplication', true, true);
			
			XXX_FileSystem_Local::setDirectoryOwnerAdvanced('/application/development', 'apache', 'intermediateApplication', true, true);
		
		// Permissions
			
			XXX_FileSystem_Local::setDirectoryPermissions('/application', '771');
			
			XXX_FileSystem_Local::setDirectoryPermissions('/application/data', '770');
			XXX_FileSystem_Local::setDirectoryPermissions('/application/data/backUps', '770', true);			
			XXX_FileSystem_Local::setFilePermissionsInDirectory('/application/data/backUps', '660', true);
			XXX_FileSystem_Local::setDirectoryPermissions('/application/data/mySQL', '770', true);	
			XXX_FileSystem_Local::setFilePermissionsInDirectory('/application/data/mySQL', '760', true);
			XXX_FileSystem_Local::setDirectoryPermissions('/application/data/ftpImportExport', '770', true);
			XXX_FileSystem_Local::setFilePermissionsInDirectory('/application/data/ftpImportExport', '660', true);		
			XXX_FileSystem_Local::setDirectoryPermissions('/application/data/logs', '711');		
			XXX_FileSystem_Local::setDirectoryPermissions('/application/data/logs/application', '711', true);	
			XXX_FileSystem_Local::setFilePermissionsInDirectory('/application/data/logs/application', '660', true);						
			XXX_FileSystem_Local::setFilePermissionsInDirectory('/application/data/userFiles', '660', true);			
		
			XXX_FileSystem_Local::setDirectoryPermissions('/application/source', '770', true);
			XXX_FileSystem_Local::setFilePermissionsInDirectory('/application/source/static', '660', true);
			XXX_FileSystem_Local::setFilePermissionsInDirectory('/application/source/dynamic', '660', true);
			XXX_FileSystem_Local::setFilePermissionsInDirectory('/application/source/apache', '660', true);
			XXX_FileSystem_Local::setFilePermissionsInDirectory('/application/source/documentation', '660', true);
		
			XXX_FileSystem_Local::setFilePermissionsInDirectory('/application/development', '660', true);
	}
	
	
	public static function correctDirectoryStructure ()
	{
		XXX_FileSystem_Local::createDirectory('/application/data');
		XXX_FileSystem_Local::createDirectory('/application/data/backUps');
		XXX_FileSystem_Local::createDirectory('/application/data/cache');
		XXX_FileSystem_Local::createDirectory('/application/data/userFiles');
		XXX_FileSystem_Local::createDirectory('/application/data/userFiles/httpFileUploads');
		XXX_FileSystem_Local::createDirectory('/application/data/userFiles/httpFileUploads/globalFileSystemStorageQueue');
		XXX_FileSystem_Local::createDirectory('/application/data/userFiles/httpFileUploads/temporary');
		XXX_FileSystem_Local::createDirectory('/application/data/userFiles/fileShards');
		XXX_FileSystem_Local::createDirectory('/application/data/quarantinedFiles');
		XXX_FileSystem_Local::createDirectory('/application/data/mySQL');
		XXX_FileSystem_Local::createDirectory('/application/data/mySQL/raw');
		XXX_FileSystem_Local::createDirectory('/application/data/logs');
		XXX_FileSystem_Local::createDirectory('/application/data/logs/mySQL');
		XXX_FileSystem_Local::createDirectory('/application/data/logs/apache');
		XXX_FileSystem_Local::createDirectory('/application/data/logs/clamAV');
		XXX_FileSystem_Local::createDirectory('/application/data/logs/php');
		XXX_FileSystem_Local::createDirectory('/application/data/logs/application');
		
		XXX_FileSystem_Local::createDirectory('/application/source');
		XXX_FileSystem_Local::createDirectory('/application/source/apache');
		XXX_FileSystem_Local::createDirectory('/application/source/apache/configuration');
		XXX_FileSystem_Local::createDirectory('/application/source/apache/certificatesAndKeys');
		XXX_FileSystem_Local::createDirectory('/application/source/documentation');
		XXX_FileSystem_Local::createDirectory('/application/source/dynamic/core');
		XXX_FileSystem_Local::createDirectory('/application/source/dynamic/entryPoint');
		XXX_FileSystem_Local::createDirectory('/application/source/static');
		XXX_FileSystem_Local::createDirectory('/application/source/static/core');
		XXX_FileSystem_Local::createDirectory('/application/source');
		XXX_FileSystem_Local::createDirectory('/application/source');
		XXX_FileSystem_Local::createDirectory('/application/source');
		XXX_FileSystem_Local::createDirectory('/application/source');
	}
}

?>