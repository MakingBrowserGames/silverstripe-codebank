<?php
class CodeBankConfig extends DataObject {
    public static $db=array(
                            'IPMessage'=>'HTMLText',
                            'Version'=>'Varchar(30)'
                         );
    
    protected static $_currentConfig;
    
    /**
     * Creates the default code bank config
     */
    public function requireDefaultRecords() {
        parent::requireDefaultRecords();
        
        
        if(!CodeBankConfig::get()->first()) {
            $conf=new CodeBankConfig();
            $conf->Version=CB_VERSION.' '.CB_BUILD_DATE;
            $conf->write();
            
            DB::alteration_message('Default Code Bank Config Created', 'created');
        }
        
        
        if(!Group::get()->filter('Code', 'code-bank-api')->first()) {
            $group=new Group();
            $group->Title='Code Bank Users';
            $group->Description='Code Bank Access Group';
            $group->Code='code-bank-api';
            $group->write();
            
            $permission=new Permission();
            $permission->Code='CODE_BANK_ACCESS';
            $permission->Type=1;
            $permission->GroupID=$group->ID;
            $permission->write();
            
            DB::alteration_message('Code Bank Users Group Created', 'created');
        }
        
        
        //Check for and perform any needed updates
        if(CB_VERSION!='@@VERSION@@' && CodeBankConfig::CurrentConfig()->Version!=CB_VERSION.' '.CB_BUILD_DATE) {
            $updateXML=simplexml_load_string(file_get_contents('http://update.edchipman.ca/codeBank/airUpdate.xml'));
            $latestVersion=strip_tags($updateXML->version->asXML());
            $versionTmp=explode(' ', $latestVersion);
            
            //Sanity Check code version against latest
            if($versionTmp[1]<CB_BUILD_DATE) {
                DB::alteration_message('Unknown Code Bank server version '.CB_VERSION.' '.CB_BUILD_DATE.', current version available for download is '.$latestVersion, 'error');
                return;
            }
            
            //Sanity Check make sure latest version is installed
            if(CB_VERSION.' '.CB_BUILD_DATE!=$latestVersion) {
                DB::alteration_message('A Code Bank Server update is available, please <a href="http://programs.edchipman.ca/applications/code-bank/">download</a> and install the update then run dev/build again.', 'error');
                return;
            }
            
            //Sanity Check database version against latest
            $dbVerTmp=explode(' ', $dbVersion);
            if($versionTmp[1]<CodeBankConfig::CurrentConfig()->Version) {
                DB::alteration_message('Code Bank Server database version '.$dbVersion.', current version available for download is '.$latestVersion, 'error');
                return;
            }
            
            
            $data=array(
                        'version'=>CodeBankConfig::CurrentConfig()->Version,
                        'db_type'=>'SERVER'
                    );
            
            $data=http_build_query($data);
            
            
            $context=stream_context_create(array(
                                                'http'=>array(
                                                            'method'=>'POST',
                                                            'header'=>"Content-type: application/x-www-form-urlencoded\r\n"
                                                                        ."Content-Length: ".strlen($data)."\r\n",
                                                            'content'=>$data
                                                        )
                                            ));
            
            
            //Download and run queries needed
            $sql=simplexml_load_string(file_get_contents('http://update.edchipman.ca/codeBank/DatabaseUpgrade.php', false, $context));
            $sets=count($sql->query);
            foreach($sql->query as $query) {
                $queries=explode('$',$query);
                $t=count($queries);
            
                foreach($queries as $query) {
                    if(empty($query)) {
                        continue;
                    }
            
                    DB::query($query);
                }
            }
            
            
            //Update Database Version
            $codeBankConfig=CodeBankConfig::CurrentConfig();
            $codeBankConfig->Version=$latestVersion;
            $codeBankConfig->write();
            
            
            DB::alteration_message('Code Bank Server database upgraded', 'changed');
        }
    }
    
    /**
     * Gets the current config
     * @return {CodeBankConfig} Code Bank Config Data
     */
    public static function CurrentConfig() {
        if(empty(self::$_currentConfig)) {
            self::$_currentConfig=CodeBankConfig::get()->first();
        }
        
        return self::$_currentConfig;
    }
    
    
    /**
     * Gets fields used in the cms
     * @return {FieldList} Fields to be used
     */
    public function getCMSFields() {
        $langGridConfig=GridFieldConfig_RecordEditor::create(30);
        $langGridConfig->getComponentByType('GridFieldDataColumns')->setFieldCasting(array(
                                                                                            'UserLanguage'=>'Boolean->Nice'
                                                                                        ));
        
        
        return new FieldList(
                            new TabSet('Root',
                                            new Tab('Main', _t('CodeBankConfig.MAIN', '_Main'),
                                                    HtmlEditorField::create('IPMessage', _t('CodeBankConfig.IP_MESSAGE', '_Intellectual Property Message'))->addExtraClass('stacked')
                                                ),
                                            new Tab('Languages', _t('CodeBankConfig.LANGUAGES', '_Languages'),
                                                    new GridField('Languages', _t('CodeBankConfig.LANGUAGES', '_Languages'), SnippetLanguage::get(), $langGridConfig)
                                                )
                                        )
                        );
    }
}
?>