<?php
/**
 * 
 * @author mojeb
 *
 */

class smileBinaryFileType extends eZDataType
{
    const MAX_FILESIZE_FIELD = 'data_int1';

    const MAX_FILESIZE_VARIABLE = '_smilebinaryfile_max_filesize_';

    const DATA_TYPE_STRING = "smilebinaryfile";

    function smileBinaryFileType()
    {
        $this->eZDataType( self::DATA_TYPE_STRING, ezpI18n::tr( 'kernel/classes/datatypes', "Smile File"),
                           array( 'serialize_supported' => true ) );
    }

    /*!
     \return the binary file handler.
    */
    function fileHandler()
    {
        return smileCollectionBinaryFileHandler::instance();
    }

    /*!
     \return the template name which the handler decides upon.
    */
    function viewTemplate( $contentobjectAttribute )
    {
        $handler = $this->fileHandler();
        $handlerTemplate = $handler->viewTemplate( $contentobjectAttribute );
        $template = $this->DataTypeString;
        if ( $handlerTemplate !== false )
            $template .= '_' . $handlerTemplate;
        return $template;
    }

    /*!
     \return the template name to use for editing the attribute.
     \note Default is to return the datatype string which is OK
           for most datatypes, if you want dynamic templates
           reimplement this function and return a template name.
     \note The returned template name does not include the .tpl extension.
     \sa viewTemplate, informationTemplate
    */
    function editTemplate( $contentobjectAttribute )
    {
        $handler = $this->fileHandler();
        $handlerTemplate = $handler->editTemplate( $contentobjectAttribute );
        $template = $this->DataTypeString;
        if ( $handlerTemplate !== false )
            $template .= '_' . $handlerTemplate;
        return $template;
    }

    /*!
     \return the template name to use for information collection for the attribute.
     \note Default is to return the datatype string which is OK
           for most datatypes, if you want dynamic templates
           reimplement this function and return a template name.
     \note The returned template name does not include the .tpl extension.
     \sa viewTemplate, editTemplate
    */
    function informationTemplate( $contentobjectAttribute )
    {
        $handler = $this->fileHandler();
        $handlerTemplate = $handler->informationTemplate( $contentobjectAttribute );
        $template = $this->DataTypeString;
        if ( $handlerTemplate !== false )
            $template .= '_' . $handlerTemplate;
        return $template;
    }

    /*!
     Sets value according to current version
    */
    function initializeObjectAttribute( $contentObjectAttribute, $currentVersion, $originalContentObjectAttribute )
    {
        if ( $currentVersion != false )
        {
            $contentObjectAttributeID = $originalContentObjectAttribute->attribute( "id" );
            $version = $contentObjectAttribute->attribute( "version" );
            $oldfile = eZBinaryFile::fetch( $contentObjectAttributeID, $currentVersion );
            if ( $oldfile != null )
            {
                $oldfile->setAttribute( 'contentobject_attribute_id', $contentObjectAttribute->attribute( 'id' ) );
                $oldfile->setAttribute( "version",  $version );
                $oldfile->store();
            }
        }
    }

    /*!
     The object is being moved to trash, do any necessary changes to the attribute.
     Rename file and update db row with new name, so that access to the file using old links no longer works.
    */
    function trashStoredObjectAttribute( $contentObjectAttribute, $version = null )
    {
        $contentObjectAttributeID = $contentObjectAttribute->attribute( "id" );
        $sys = eZSys::instance();
        $storage_dir = $sys->storageDirectory();

        if ( $version == null )
            $binaryFiles = eZBinaryFile::fetch( $contentObjectAttributeID );
        else
            $binaryFiles = array( eZBinaryFile::fetch( $contentObjectAttributeID, $version ) );

        foreach ( $binaryFiles as $binaryFile )
        {
            if ( $binaryFile == null )
                continue;
            $mimeType =  $binaryFile->attribute( "mime_type" );
            list( $prefix, $suffix ) = explode( '/', $mimeType );
            $orig_dir = $storage_dir . '/original/' . $prefix;
            $fileName = $binaryFile->attribute( "filename" );

            // Check if there are any other records in ezbinaryfile that point to that fileName.
            $binaryObjectsWithSameFileName = eZBinaryFile::fetchByFileName( $fileName );

            $filePath = $orig_dir . "/" . $fileName;
            $file = eZClusterFileHandler::instance( $filePath );

            if ( $file->exists() and count( $binaryObjectsWithSameFileName ) <= 1 )
            {
                // create dest filename in the same manner as eZHTTPFile::store()
                // grab file's suffix
                $fileSuffix = eZFile::suffix( $fileName );
                // prepend dot
                if ( $fileSuffix )
                    $fileSuffix = '.' . $fileSuffix;
                // grab filename without suffix
                $fileBaseName = basename( $fileName, $fileSuffix );
                // create dest filename
                $newFileName = md5( $fileBaseName . microtime() . mt_rand() ) . $fileSuffix;
                $newFilePath = $orig_dir . "/" . $newFileName;

                // rename the file, and update the database data
                $file->move( $newFilePath );
                $binaryFile->setAttribute( 'filename', $newFileName );
                $binaryFile->store();
            }
        }
    }
    /*!
     Delete stored attribute
    */
     function deleteStoredObjectAttribute( $contentObjectAttribute, $version = null )
    {
        self::deleteObjectAttribute($contentObjectAttribute, $version );
    }
    /*!
     Delete stored attribute
    */
    static function deleteObjectAttribute( $contentObjectAttribute, $version = null )
    {
        $contentObjectAttributeID = $contentObjectAttribute->attribute( "id" );
        $sys = eZSys::instance();
        $storage_dir = $sys->storageDirectory();

        if ( $version == null )
        {
            $binaryFiles = eZBinaryFile::fetch( $contentObjectAttributeID );
            eZBinaryFile::removeByID( $contentObjectAttributeID, null );

            foreach ( $binaryFiles as  $binaryFile )
            {
                $mimeType =  $binaryFile->attribute( "mime_type" );
                list( $prefix, $suffix ) = explode('/', $mimeType );
                $orig_dir = $storage_dir . '/original/' . $prefix;
                $fileName = $binaryFile->attribute( "filename" );

                // Check if there are any other records in ezbinaryfile that point to that fileName.
                $binaryObjectsWithSameFileName = eZBinaryFile::fetchByFileName( $fileName );

                $filePath = $orig_dir . "/" . $fileName;
                $file = eZClusterFileHandler::instance( $filePath );

                if ( $file->exists() and count( $binaryObjectsWithSameFileName ) < 1 )
                    $file->delete();
            }
        }
        else
        {
            $count = 0;
            $binaryFile = eZBinaryFile::fetch( $contentObjectAttributeID, $version );
            
            if ( $binaryFile != null )
            {
                $mimeType =  $binaryFile->attribute( "mime_type" );
                list( $prefix, $suffix ) = explode('/', $mimeType );
                $orig_dir = $storage_dir . "/original/" . $prefix;
                $fileName = $binaryFile->attribute( "filename" );

                eZBinaryFile::removeByID( $contentObjectAttributeID, $version );

                // Check if there are any other records in ezbinaryfile that point to that fileName.
                $binaryObjectsWithSameFileName = eZBinaryFile::fetchByFileName( $fileName );

                $filePath = $orig_dir . "/" . $fileName;
                $file = eZClusterFileHandler::instance( $filePath );

                if ( $file->exists() and count( $binaryObjectsWithSameFileName ) < 1 )
                    $file->delete();
            }
        }
    }

    /*!
     Checks if file uploads are enabled, if not it gives a warning.
    */
    function checkFileUploads()
    {
        $isFileUploadsEnabled = ini_get( 'file_uploads' ) != 0;
        if ( !$isFileUploadsEnabled )
        {
            $isFileWarningAdded = $GLOBALS['smileBinaryFileTypeWarningAdded'];
            if ( !isset( $isFileWarningAdded ) or
                 !$isFileWarningAdded )
            {
                eZAppendWarningItem( array( 'error' => array( 'type' => 'kernel',
                                                              'number' => eZError::KERNEL_NOT_AVAILABLE ),
                                            'text' => ezpI18n::tr( 'kernel/classes/datatypes',
                                                              'File uploading is not enabled. Please contact the site administrator to enable it.' ) ) );
                $GLOBALS['smileBinaryFileTypeWarningAdded'] = true;
            }
        }
    }

    /*!
     Validates the input and returns true if the input was
     valid for this datatype.
    */
    function validateObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute )
    {
        smileBinaryFileType::checkFileUploads();
        $classAttribute = $contentObjectAttribute->contentClassAttribute();
        $mustUpload = false;
        $httpFileName = $base . "_data_smilebinaryfilename_" . $contentObjectAttribute->attribute( "id" );
        $maxSize = 1024 * 1024 * $classAttribute->attribute( self::MAX_FILESIZE_FIELD );

        if ( $contentObjectAttribute->validateIsRequired() )
        {
            $contentObjectAttributeID = $contentObjectAttribute->attribute( "id" );
            $version = $contentObjectAttribute->attribute( "version" );
            $binary = eZBinaryFile::fetch( $contentObjectAttributeID, $version );
            if ( $binary === null )
            {
                $mustUpload = true;
            }
        }

        $canFetchResult = eZHTTPFile::canFetch( $httpFileName, $maxSize );
        if ( $mustUpload && $canFetchResult == eZHTTPFile::UPLOADEDFILE_DOES_NOT_EXIST )
        {
            $contentObjectAttribute->setValidationError( ezpI18n::tr( 'kernel/classes/datatypes',
                                                                 'A valid file is required.' ) );
            return eZInputValidator::STATE_INVALID;
        }
        if ( $canFetchResult == eZHTTPFile::UPLOADEDFILE_EXCEEDS_PHP_LIMIT )
        {
            $contentObjectAttribute->setValidationError( ezpI18n::tr( 'kernel/classes/datatypes',
                'The size of the uploaded file exceeds the limit set by the upload_max_filesize directive in php.ini.' ) );
            return eZInputValidator::STATE_INVALID;
        }
        if ( $canFetchResult == eZHTTPFile::UPLOADEDFILE_EXCEEDS_MAX_SIZE )
        {
            $contentObjectAttribute->setValidationError( ezpI18n::tr( 'kernel/classes/datatypes',
                                                                 'The size of the uploaded file exceeds the maximum upload size: %1 bytes.' ), $maxSize );
            return eZInputValidator::STATE_INVALID;
        }
        
        //check file extension and mime type
        
        $ini                     = eZINI::instance( 'smilebinaryfile.ini.append.php');
        $allowedFileTypeList     = $ini->variable('AllowedFileTypes', 'AllowedFileTypeList');
        $allowedFileMimeTypeList = $ini->variable('AllowedFileTypes', 'AllowedFileMimeTypeList');
       
        if(isset($_FILES[$httpFileName]) AND $_FILES[$httpFileName]["name"] != '')
        {
            $extension = explode('.', $_FILES[$httpFileName]["name"]);
            $extension = end($extension);
    
            if (!in_array(strtolower($extension),$allowedFileTypeList))
            {
                $contentObjectAttribute->setValidationError( ezpI18n::tr( 'kernel/classes/datatypes','Failed to store file. Only the following file types are allowed: %1.' ), implode(", ",$allowedFileTypeList) );
                return eZInputValidator::STATE_INVALID;
            }

            if ( isset( $_FILES[$httpFileName] )
              AND
             $_FILES[$httpFileName]["name"] != ""
                    AND
             !in_array($_FILES[$httpFileName]['type'], $allowedFileMimeTypeList)
            )
            {
                $contentObjectAttribute->setValidationError( ezpI18n::tr( 'kernel/classes/datatypes','Failed to store file. Only the following file types are allowed: %1.' ), implode(", ",$allowedFileTypeList) );
                return eZInputValidator::STATE_INVALID;
            }
        }

        return eZInputValidator::STATE_ACCEPTED;
    }

    /*!
     Fetches the http post var integer input and stores it in the data instance.
    */
    function fetchObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute )
    {
        smileBinaryFileType::checkFileUploads();
        if ( !eZHTTPFile::canFetch( $base . "_data_smilebinaryfilename_" . $contentObjectAttribute->attribute( "id" ) ) )
            return false;

        $binaryFile = eZHTTPFile::fetch( $base . "_data_smilebinaryfilename_" . $contentObjectAttribute->attribute( "id" ) );

        $contentObjectAttribute->setContent( $binaryFile );

        if ( $binaryFile instanceof eZHTTPFile )
        {
            $contentObjectAttributeID = $contentObjectAttribute->attribute( "id" );
            $version = $contentObjectAttribute->attribute( "version" );

            /*
            $mimeObj = new  eZMimeType();
            $mimeData = $mimeObj->findByURL( $binaryFile->attribute( "original_filename" ), true );
            $mime = $mimeData['name'];
            */

            $mimeData = eZMimeType::findByFileContents( $binaryFile->attribute( "original_filename" ) );
            $mime = $mimeData['name'];

            if ( $mime == '' )
            {
                $mime = $binaryFile->attribute( "mime_type" );
            }
            $extension = eZFile::suffix( $binaryFile->attribute( "original_filename" ) );
            $binaryFile->setMimeType( $mime );
            if ( !$binaryFile->store( "original", $extension ) )
            {
                eZDebug::writeError( "Failed to store http-file: " . $binaryFile->attribute( "original_filename" ),
                                     "smileBinaryFileType" );
                return false;
            }

            $binary = eZBinaryFile::fetch( $contentObjectAttributeID, $version );
            if ( $binary === null )
                $binary = eZBinaryFile::create( $contentObjectAttributeID, $version );

            $orig_dir = $binaryFile->storageDir( "original" );

            $binary->setAttribute( "contentobject_attribute_id", $contentObjectAttributeID );
            $binary->setAttribute( "version", $version );
            $binary->setAttribute( "filename", basename( $binaryFile->attribute( "filename" ) ) );
            $binary->setAttribute( "original_filename", $binaryFile->attribute( "original_filename" ) );
            $binary->setAttribute( "mime_type", $mime );

            $binary->store();

            $filePath = $binaryFile->attribute( 'filename' );
            $fileHandler = eZClusterFileHandler::instance();
            $fileHandler->fileStore( $filePath, 'binaryfile', true, $mime );

            $contentObjectAttribute->setContent( $binary );
        }
        return true;
    }

    /*!
     Does nothing, since the file has been stored. See fetchObjectAttributeHTTPInput for the actual storing.
    */
    function storeObjectAttribute( $contentObjectAttribute )
    {
    }

    function customObjectAttributeHTTPAction( $http, $action, $contentObjectAttribute, $parameters )
    {
        smileBinaryFileType::checkFileUploads();
        if( $action == "delete_binary" )
        {
            $contentObjectAttributeID = $contentObjectAttribute->attribute( "id" );
            $version = $contentObjectAttribute->attribute( "version" );
            $this->deleteStoredObjectAttribute( $contentObjectAttribute, $version );
        }
    }

    /*!
     HTTP file insertion is supported.
    */
    function isHTTPFileInsertionSupported()
    {
        return true;
    }

    /*!
     HTTP file insertion is supported.
    */
    function isRegularFileInsertionSupported()
    {
        return true;
    }

    /*!
     Inserts the file using the eZBinaryFile class.
    */
    function insertHTTPFile( $object, $objectVersion, $objectLanguage,
                             $objectAttribute, $httpFile, $mimeData,
                             &$result )
    {
        $result = array( 'errors' => array(),
                         'require_storage' => false );
        $attributeID = $objectAttribute->attribute( 'id' );

        $binary = eZBinaryFile::fetch( $attributeID, $objectVersion );
        if ( $binary === null )
            $binary = eZBinaryFile::create( $attributeID, $objectVersion );

        $httpFile->setMimeType( $mimeData['name'] );

        $db = eZDB::instance();
        $db->begin();

        if ( !$httpFile->store( "original", false, false ) )
        {
            $result['errors'][] = array( 'description' => ezpI18n::tr( 'kernel/classes/datatypes/ezbinaryfile',
                                                        'Failed to store file %filename. Please contact the site administrator.', null,
                                                        array( '%filename' => $httpFile->attribute( "original_filename" ) ) ) );
            return false;
        }


        $filePath = $binary->attribute( 'filename' );

        $binary->setAttribute( "contentobject_attribute_id", $attributeID );
        $binary->setAttribute( "version", $objectVersion );
        $binary->setAttribute( "filename", basename( $httpFile->attribute( "filename" ) ) );
        $binary->setAttribute( "original_filename", $httpFile->attribute( "original_filename" ) );
        $binary->setAttribute( "mime_type", $mimeData['name'] );

        $binary->store();

        $filePath = $httpFile->attribute( 'filename' );
        $fileHandler = eZClusterFileHandler::instance();
        $fileHandler->fileStore( $filePath, 'binaryfile', true, $mimeData['name'] );
        $objectAttribute->setContent( $binary );

        $db->commit();

        $objectAttribute->setContent( $binary );

        return true;
    }

    /*!
     Inserts the file using the eZBinaryFile class.
    */
    function insertRegularFile( $object, $objectVersion, $objectLanguage,
                                $objectAttribute, $filePath,
                                &$result )
    {
        $result = array( 'errors' => array(),
                         'require_storage' => false );
        $attributeID = $objectAttribute->attribute( 'id' );

        $binary = eZBinaryFile::fetch( $attributeID, $objectVersion );
        if ( $binary === null )
            $binary = eZBinaryFile::create( $attributeID, $objectVersion );

        $fileName = basename( $filePath );
        $mimeData = eZMimeType::findByFileContents( $filePath );
        $storageDir = eZSys::storageDirectory();
        list( $group, $type ) = explode( '/', $mimeData['name'] );
        $destination = $storageDir . '/original/' . $group;

        if ( !file_exists( $destination ) )
        {
            if ( !eZDir::mkdir( $destination, false, true ) )
            {
                return false;
            }
        }

        // create dest filename in the same manner as eZHTTPFile::store()
        // grab file's suffix
        $fileSuffix = eZFile::suffix( $fileName );
        // prepend dot
        if( $fileSuffix )
            $fileSuffix = '.' . $fileSuffix;
        // grab filename without suffix
        $fileBaseName = basename( $fileName, $fileSuffix );
        // create dest filename
        $destFileName = md5( $fileBaseName . microtime() . mt_rand() ) . $fileSuffix;
        $destination = $destination . '/' . $destFileName;

        copy( $filePath, $destination );

        $fileHandler = eZClusterFileHandler::instance();
        $fileHandler->fileStore( $destination, 'binaryfile', true, $mimeData['name'] );


        $binary->setAttribute( "contentobject_attribute_id", $attributeID );
        $binary->setAttribute( "version", $objectVersion );
        $binary->setAttribute( "filename", $destFileName );
        $binary->setAttribute( "original_filename", $fileName );
        $binary->setAttribute( "mime_type", $mimeData['name'] );

        $binary->store();

        $objectAttribute->setContent( $binary );
        return true;
    }

    /*!
      We support file information
    */
    function hasStoredFileInformation( $object, $objectVersion, $objectLanguage,
                                       $objectAttribute )
    {
        return true;
    }

    /*!
      Extracts file information for the binaryfile entry.
    */
    function storedFileInformation( $object, $objectVersion, $objectLanguage,
                                    $objectAttribute )
    {
        if($object instanceof  eZInformationCollection)
        {
            $contentObjectAttributeID = $objectAttribute->attribute( 'id' );
            $informationCollectionID  = $object->attribute( 'id' );
            $binaryFile               = smileCollectionBinaryFile::fetch( $informationCollectionID, $contentObjectAttributeID);
        }
        else 
        {
            $binaryFile = eZBinaryFile::fetch( $objectAttribute->attribute( "id" ),
                                            $objectAttribute->attribute( "version" ) );
        }

        if ( $binaryFile )
        {
            return $binaryFile->storedFileInfo();
        }
        return false;
    }
    /*!
      Updates download count for binary file.
    */
    function handleDownload( $object, $objectVersion, $objectLanguage,
                             $objectAttribute )
    {
        if( $object instanceof eZInformationCollectionAttribute)
        {
            $contentObjectAttributeID = $objectAttribute->attribute( 'id' );
            $informationCollectionID  = $informationCollection->attribute( 'id' );
            
            $binaryFile = smileCollectionBinaryFile::fetch( $informationCollectionID, $contentObjectAttributeID);
            
            
            if ( $binaryFile )
            {
                $db = eZDB::instance();
                $db->query( "UPDATE smileCollectionbinaryfile SET download_count=(download_count+1)
                        WHERE
                        contentobject_attribute_id=$contentObjectAttributeID AND informationcollection_id=$informationCollectionID" );
                return true;
            }
        }
        else 
        {
            $binaryFile = eZBinaryFile::fetch( $objectAttribute->attribute( "id" ),
                                                $objectAttribute->attribute( "version" ) );
    
            $contentObjectAttributeID = $objectAttribute->attribute( 'id' );
            $version =  $objectAttribute->attribute( "version" );
    
            if ( $binaryFile )
            {
                $db = eZDB::instance();
                $db->query( "UPDATE ezbinaryfile SET download_count=(download_count+1)
                             WHERE
                             contentobject_attribute_id=$contentObjectAttributeID AND version=$version" );
                return true;
            }
        }
        return false;
    }

    
    function fetchClassAttributeHTTPInput( $http, $base, $classAttribute )
    {
        $filesizeName = $base . self::MAX_FILESIZE_VARIABLE . $classAttribute->attribute( 'id' );
        if ( $http->hasPostVariable( $filesizeName ) )
        {
            $filesizeValue = $http->postVariable( $filesizeName );
            $classAttribute->setAttribute( self::MAX_FILESIZE_FIELD, $filesizeValue );
        }
    }
    /*!
     Returns the object title.
    */
    function title( $contentObjectAttribute,  $name = "original_filename" )
    {
        $value = false;
        $binaryFile = eZBinaryFile::fetch( $contentObjectAttribute->attribute( 'id' ),
                                           $contentObjectAttribute->attribute( 'version' ) );
        if ( is_object( $binaryFile ) )
            $value = $binaryFile->attribute( $name );

        return $value;
    }

    function hasObjectAttributeContent( $contentObjectAttribute )
    {
        $binaryFile = eZBinaryFile::fetch( $contentObjectAttribute->attribute( "id" ),
                                            $contentObjectAttribute->attribute( "version" ) );
        if ( !$binaryFile )
            return false;
        return true;
    }

    function objectAttributeContent( $contentObjectAttribute )
    {
        if( $contentObjectAttribute instanceof eZInformationCollectionAttribute) 
        {
            $binaryFile = smileCollectionBinaryFile::fetch( $contentObjectAttribute->attribute( "informationcollection_id" ),
                    $contentObjectAttribute->attribute( "contentobject_attribute_id" ) );
        }
        else 
        {
            $binaryFile = eZBinaryFile::fetch( $contentObjectAttribute->attribute( "id" ),
                                            $contentObjectAttribute->attribute( "version" ) );
         }
         
        if ( !$binaryFile )
        {
            $attrValue = false;
            return $attrValue;
        }
       
        return $binaryFile;
    }

    function isIndexable()
    {
        return true;
    }

    function metaData( $contentObjectAttribute )
    {
        $binaryFile = $contentObjectAttribute->content();

        $metaData = "";
        if ( $binaryFile instanceof eZBinaryFile )
        {
            $metaData = $binaryFile->metaData();
        }
        return $metaData;
    }

    function serializeContentClassAttribute( $classAttribute, $attributeNode, $attributeParametersNode )
    {
        $dom = $attributeParametersNode->ownerDocument;
        $maxSize = $classAttribute->attribute( self::MAX_FILESIZE_FIELD );
        $maxSizeNode = $dom->createElement( 'max-size' );
        $maxSizeNode->appendChild( $dom->createTextNode( $maxSize ) );
        $maxSizeNode->setAttribute( 'unit-size', 'mega' );
        $attributeParametersNode->appendChild( $maxSizeNode );
    }

    function unserializeContentClassAttribute( $classAttribute, $attributeNode, $attributeParametersNode )
    {
        $sizeNode = $attributeParametersNode->getElementsByTagName( 'max-size' )->item( 0 );
        $maxSize = $sizeNode->textContent;
        $unitSize = $sizeNode->getAttribute( 'unit-size' );
        $classAttribute->setAttribute( self::MAX_FILESIZE_FIELD, $maxSize );
    }

    /*!
     \return string representation of an contentobjectattribute data for simplified export

    */
    function toString( $objectAttribute )
    {
        $binaryFile = $objectAttribute->content();

        if ( is_object( $binaryFile ) )
        {
            return implode( '|', array( $binaryFile->attribute( 'filepath' ), $binaryFile->attribute( 'original_filename' ) ) );
        }
        else
            return '';
    }



    function fromString( $objectAttribute, $string )
    {
        if( !$string )
            return true;

        $result = array();
        return $this->insertRegularFile( $objectAttribute->attribute( 'object' ),
                                         $objectAttribute->attribute( 'version' ),
                                         $objectAttribute->attribute( 'language_code' ),
                                         $objectAttribute,
                                         $string,
                                         $result );
    }

    function serializeContentObjectAttribute( $package, $objectAttribute )
    {
        $node = $this->createContentObjectAttributeDOMNode( $objectAttribute );

        $binaryFile = $objectAttribute->attribute( 'content' );
        if ( is_object( $binaryFile ) )
        {
            $fileKey = md5( mt_rand() );
            $package->appendSimpleFile( $fileKey, $binaryFile->attribute( 'filepath' ) );

            $dom = $node->ownerDocument;
            $fileNode = $dom->createElement( 'binary-file' );
            $fileNode->setAttribute( 'filesize', $binaryFile->attribute( 'filesize' ) );
            $fileNode->setAttribute( 'filename', $binaryFile->attribute( 'filename' ) );
            $fileNode->setAttribute( 'original-filename', $binaryFile->attribute( 'original_filename' ) );
            $fileNode->setAttribute( 'mime-type', $binaryFile->attribute( 'mime_type' ) );
            $fileNode->setAttribute( 'filekey', $fileKey );
            $node->appendChild( $fileNode );
        }

        return $node;
    }

    function unserializeContentObjectAttribute( $package, $objectAttribute, $attributeNode )
    {
        $fileNode = $attributeNode->getElementsByTagName( 'binary-file' )->item( 0 );
        if ( !is_object( $fileNode ) or !$fileNode->hasAttributes() )
        {
            return;
        }

        $binaryFile = eZBinaryFile::create( $objectAttribute->attribute( 'id' ), $objectAttribute->attribute( 'version' ) );

        $sourcePath = $package->simpleFilePath( $fileNode->getAttribute( 'filekey' ) );

        if ( !file_exists( $sourcePath ) )
        {
            eZDebug::writeError( "The file '$sourcePath' does not exist, cannot initialize file attribute with it", __METHOD__ );
            return false;
        }

        $ini = eZINI::instance();
        $mimeType = $fileNode->getAttribute( 'mime-type' );
        list( $mimeTypeCategory, $mimeTypeName ) = explode( '/', $mimeType );
        $destinationPath = eZSys::storageDirectory() . '/original/' . $mimeTypeCategory . '/';
        if ( !file_exists( $destinationPath ) )
        {
            $oldumask = umask( 0 );
            if ( !eZDir::mkdir( $destinationPath, false, true ) )
            {
                umask( $oldumask );
                return false;
            }
            umask( $oldumask );
        }

        $basename = basename( $fileNode->getAttribute( 'filename' ) );
        while ( file_exists( $destinationPath . $basename ) )
        {
            $basename = substr( md5( mt_rand() ), 0, 8 ) . '.' . eZFile::suffix( $fileNode->getAttribute( 'filename' ) );
        }

        eZFileHandler::copy( $sourcePath, $destinationPath . $basename );
        eZDebug::writeNotice( 'Copied: ' . $sourcePath . ' to: ' . $destinationPath . $basename, __METHOD__ );

        $binaryFile->setAttribute( 'contentobject_attribute_id', $objectAttribute->attribute( 'id' ) );
        $binaryFile->setAttribute( 'filename', $basename );
        $binaryFile->setAttribute( 'original_filename', $fileNode->getAttribute( 'original-filename' ) );
        $binaryFile->setAttribute( 'mime_type', $fileNode->getAttribute( 'mime-type' ) );

        $binaryFile->store();

        $fileHandler = eZClusterFileHandler::instance();
        $fileHandler->fileStore( $destinationPath . $basename, 'binaryfile', true );
    }

    function supportsBatchInitializeObjectAttribute()
    {
        return true;
    }
    /**
     * (non-PHPdoc)
     * @param eZHTTPTool $http
     * @see eZDataType::validateCollectionAttributeHTTPInput()
     */
    function validateCollectionAttributeHTTPInput( $http, $base, $contentObjectAttribute )
    {
        return $this->validateObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute );
    }
    
    /**
     * (non-PHPdoc)
     * @param eZContentObjectAttribute $contentObjectAttribute
     * @see eZDataType::fetchCollectionAttributeHTTPInput()
     */
    function fetchCollectionAttributeHTTPInput( $collection, $collectionAttribute, $http, $base, $contentObjectAttribute )
    {
        smileBinaryFileType::checkFileUploads();
        if ( !eZHTTPFile::canFetch( $base . "_data_smilebinaryfilename_" . $contentObjectAttribute->attribute( "id" ) ) )
            return false;
        
        $binaryFile = eZHTTPFile::fetch( $base . "_data_smilebinaryfilename_" . $contentObjectAttribute->attribute( "id" ) );

        if ( $binaryFile instanceof eZHTTPFile )
        {
            $contentObjectAttributeID = $contentObjectAttribute->attribute( "id" );

            $informationCollectionID    = $collectionAttribute->attribute( "informationcollection_id" );
            $version = $contentObjectAttribute->attribute( "version" );

        
            $mimeData = eZMimeType::findByFileContents( $binaryFile->attribute( "original_filename" ) );
            $mime = $mimeData['name'];
        
            if ( $mime == '' )
            {
                $mime = $binaryFile->attribute( "mime_type" );
            }
            $extension = eZFile::suffix( $binaryFile->attribute( "original_filename" ) );
            $binaryFile->setMimeType( $mime );
            if ( !$binaryFile->store( "original", $extension ) )
            {
                eZDebug::writeError( "Failed to store http-file: " . $binaryFile->attribute( "original_filename" ),
                "smileBinaryFileType" );
                return false;
            }

            $binary = smileCollectionBinaryFile::create( $informationCollectionID, $contentObjectAttributeID);

            $orig_dir = $binaryFile->storageDir( "original" );
            $binary->setAttribute( "informationcollection_id", $informationCollectionID );
            $binary->setAttribute( "contentobject_attribute_id", $contentObjectAttributeID );
            $binary->setAttribute( "filename", basename( $binaryFile->attribute( "filename" ) ) );
            $binary->setAttribute( "original_filename", $binaryFile->attribute( "original_filename" ) );
            $binary->setAttribute( "mime_type", $mime );
        
            $binary->store();
        
            $filePath = $binaryFile->attribute( 'filename' );
            $fileHandler = eZClusterFileHandler::instance();
            $fileHandler->fileStore( $filePath, 'binaryfile', true, $mime );
        
            $collectionAttribute->setAttribute( 'data_text', '' );

        }
        return true;
    }
    function isInformationCollector()
    {
        return true;
    }
    /*!
     Delete stored attribute
    */
    static function deleteStoredInformationCollectionAttribute( $contentObjectAttribute )
    {
        $contentObjectAttributeID = $contentObjectAttribute->attribute( "id" );
        $sys = eZSys::instance();
        $storage_dir = $sys->storageDirectory();

        $binaryFile = smileCollectionBinaryFile::fetchByAttributeID( $contentObjectAttributeID );
        smileCollectionBinaryFile::removeByAttributeID( $contentObjectAttributeID );
        
        if($binaryFile!= null)
        {
            $mimeType =  $binaryFile->attribute( "mime_type" );
            list( $prefix, $suffix ) = explode('/', $mimeType );
            $orig_dir = $storage_dir . '/original/' . $prefix;
            $fileName = $binaryFile->attribute( "filename" );
        
            // Check if there are any other records in ezbinaryfile that point to that fileName.
            $binaryObjectsWithSameFileName = eZBinaryFile::fetchByFileName( $fileName );
        
            $filePath = $orig_dir . "/" . $fileName;
            $file = eZClusterFileHandler::instance( $filePath );
        
            if ( $file->exists() and count( $binaryObjectsWithSameFileName ) < 1 )
                $file->delete();
        }

    }
    
    /**
     * Return content action(s) which can be performed on object containing
     * the current datatype. Return format is array of arrays with key 'name'
     * and 'action'. 'action' can be mapped to url in datatype.ini
     *
     * @param eZContentClassAttribute $classAttribute
     * @return array
     */
    function contentActionList( $classAttribute )
    {
        $actionList = parent::contentActionList( $classAttribute );
        $actionList[] = array( 'name' => ezpI18n::tr( 'kernel/classes/datatypes', 'Delete Smile Binary File' ),
                'action' => 'ActionDeleteSmileBinaryFile'
        );

        return $actionList;
    }
}

eZDataType::register( smileBinaryFileType::DATA_TYPE_STRING, "smileBinaryFileType" );

?>
