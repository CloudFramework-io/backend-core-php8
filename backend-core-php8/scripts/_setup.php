<?php

/**
 * https://cloudframework.io
 * Script Setup to facilitate the basic configuration of the framework
 */
class Script extends Scripts2020
{
    /**
     * This function is executed as the main method of the class
     */
    function main()
    {
        $shell = trim(shell_exec('echo $SHELL'));
        $this->sendTerminal('SHELL configuration');
        $this->sendTerminal(' - current shell: ' . "[{$shell}]");

        # macOS shell
        if ($shell == '/bin/zsh') {
            $rc_file_path = trim(shell_exec('echo ~/.zshrc'));
            $rc_file_content = is_file($rc_file_path) ? file_get_contents($rc_file_path) : "#not-found";

            //region SETUP aliases and functions
            $continue = $this->promptVar(["title" => "   # Do you want auto configuration of [$rc_file_path]", "default" => "yes", "allowed_values" => ["yes", "no"]]);
            if ($continue == "no") $this->sendTerminal("   # Avoiding shell Aliases and Functions Configuration");
            else {
                $this->sendTerminal('   # Analyzing ' . $rc_file_path);
                if ($this->addAliasesAndFunctions($rc_file_content)) {
                    file_put_contents($rc_file_path, $rc_file_content);
                    $this->sendTerminal("   # Updated. Execute [source {$rc_file_path}]");
                }
            }
            //endregion

        }

        $config_json_file = $this->core->system->root_path . '/config.json';
        $config_json_file_content = json_decode(file_get_contents($config_json_file), true);
        $this->sendTerminal("CORE ./config.json");

        $this->sendTerminal(" - GCP configuration config vars");
        if ($this->verifyConfigVars($config_json_file_content)) {
            file_put_contents($config_json_file, json_encode($config_json_file_content,JSON_PRETTY_PRINT));
            $this->sendTerminal(" - Updated ./config.json");
        }

        $this->sendTerminal('OK');
    }

    /**
     * Analyze the $rc_file_content
     * @param $rc_file_content
     * @retun boolean|null it will return true if the function has added any new element. false if there are not changes or null if error
     */
    private function addAliasesAndFunctions(&$rc_file_content)
    {
        $changes = false;

        //region ADDING header/footer
        $header_tag = "# BEGIN CloudFramework ALIASES AND FUNCTIONS";
        $header_end = "# END CloudFramework ALIASES AND FUNCTIONS";
        if (!strpos($rc_file_content, $header_tag)) {
            $this->sendTerminal("     . Adding header [$header_tag]");
            $rc_file_content .= "\n{$header_tag}\n\n\n{$header_end}";
            $changes = true;
        } else {
            $this->sendTerminal("     . Found footer [$header_tag]");
        }
        if (!strpos($rc_file_content, $header_end)) {
            $this->sendTerminal("     . Adding tag [$header_end]");
            $rc_file_content .= "\n{$header_end}";
            $changes = true;
        } else {
            $this->sendTerminal("     . Found tag [$header_end]");
        }
        //endregion

        //region ADDING aliases
        $tags = [
//            '# PHP 7.4 Export variables',
//            'export PATH="/usr/local/opt/php@7.4/bin:$PATH"',
//            'export PATH="/usr/local/opt/php@7.4/sbin:$PATH"',
//            'export LDFLAGS="-L/usr/local/opt/php@7.4/lib"',
//            'export CPPFLAGS="-I/usr/local/opt/php@7.4/include"',
            '# Cloudframework ALIASES',
            "alias cfserve='composer run-script serve'",
            "alias cfdeploy='composer run-script deploy'",
            "alias cfcredentials='composer run-script install-development-credentials'",
            "alias cfscript='composer run-script script'",
            "alias cffront=\"python cf_http_dev.py 5000 'Pragma: no-cache' 'Cache-Control: no-cache' 'Expires: 0'  'Access-Control-Allow-Origin: *'\"",
//            "alias cfdownload_dev_secrets='composer run-script download-dev-secrets'",
//            "alias cfupload_dev_secrets='composer run-script upload-dev-secrets'",
//            "alias cfdownload_prod_secrets='composer run-script download-prod-secrets'",
//            "alias cfupload_prod_secrets='composer run-script upload-prod-secrets'",
            "alias cfgen_password='openssl rand -base64 21'",
            #"alias cftest='echo use cftest _test/{org};php vendor/cloudframework-io/backend-core-php8/runtest.php'",
            "alias cfreload_source='source ~/.zshrc'",
            '# Cloudframework Functions',

        ];
        foreach ($tags as $tag) {
            if (!strpos($rc_file_content, $tag)) {
                $this->sendTerminal("   # Adding alias [$tag]");
                $rc_file_content = str_replace("\n{$header_end}", " {$tag}\n\n{$header_end}", $rc_file_content);
                $changes = true;
            } else {
                $this->sendTerminal("     . Found alias [$tag]");
            }
        }
        //endregion

        //region ADDING functions
        $function = "function gcp () {";
        if (!strpos($rc_file_content, $function)) {
            $this->sendTerminal("   # Adding function [$function]");
            $function = 'function gcp () {
      local f_source="$1"
      local d_source=$(echo "${f_source}" | sed -e \'s/buckets/gs:\//\' )
      echo copying from ${f_source} to ${d_source}
      gsutil cp ${f_source} ${d_source}
 }';
            $rc_file_content = str_replace("\n{$header_end}", " {$function}\n\n{$header_end}", $rc_file_content);
        } else {
            $this->sendTerminal("     . Found function [$function]");
        }

        $function = "function gsecret () {";
        if (!strpos($rc_file_content, $function)) {
            $this->sendTerminal("   # Adding function [$function]");
            $function = ' function gsecret () {
      # verify params
      if [ "$1" != "read" ] && [ "$1" != "update" ] && [ "$1" != "list" ] && [ "$1" != "create" ]; then
         echo "Wrong command, use:"
         echo " gsecret list [{project-name}]"
         echo " gsecret create {secret-name} {file-data} [{project-name}]"
         echo " gsecret (read|update) {secret-name} [{project-name}]"
         return
      fi
    
      # read last project name
      local project=$(cat /tmp/last_secret_project)
    
      # process commands
      case $1 in
         list)
             if [[ -z $2 ]]; then
                 if [[ -z $project ]]; then
                     local project="cloudframework-io"
                 fi
             else
                 echo "$2" > /tmp/last_secret_project
                 local project="$2"
             fi
             echo "gcloud secrets list --project=\"$project\""
             echo "Listing secrets from project $project"
             gcloud secrets list --project="$project"
             return;
             ;;
         read)
             if [[ -z $2 ]]; then
                echo " gsecret read {secret-name} [{project-name}]"
                return
             fi
             if [[ -z $3 ]]; then
                if [[ -z $project ]]; then
                    local project="cloudframework-io"
                fi
             else
                echo "$3" > /tmp/last_secret_project
                local project="$3"
             fi
             echo "gcloud secrets versions access latest --secret=\"$2\" --project=\"$project\""
             echo "Reading secret $2 in project $project"
             gcloud secrets versions access latest --secret="$2" --project="$project"
             return;
             ;;
         update)
             if [[ -z $2 ]]; then
                echo " gsecret update {secret-name} {path-file-to-update} [{project-name}]"
                return
             fi
             if [[ -z $3 ]]; then
                echo " gsecret update {secret-name} {path-file-to-update} [{project-name}]"
                return
             fi
            if [[ -z $4 ]]; then
                if [[ -z $project ]]; then
                    local project="cloudframework-io"
                fi
             else
                echo "$4" > /tmp/last_secret_project
                local project="$4"
             fi
             echo "gcloud secrets versions add \"$2\" --data-file=\"$3\" --project=\"$project\""
             echo "Updating to secret $2  with file $3 in project $project. To confirm write \'y\' [N,y]"
             read confirm
             if [ "$confirm" = "y" ]; then
                gcloud secrets versions add "$2" --data-file="$3" --project="$project"
             fi
             return;
             ;;
         create)
             if [[ -z $2 ]]; then
                echo " gsecret create {secret-name} [{project-name}]"
                return
             fi
             if [[ -z $3 ]]; then
                if [[ -z $project ]]; then
                    local project="cloudframework-io"
                fi
             else
                echo "$3" > /tmp/last_secret_project
                local project="$3"
             fi
             echo "gcloud secrets create \"$2\" --replication-policy=\"automatic\" --project=\"$project\""
             echo "Creating secret $2 in project $project. To confirm write \'y\' [N,y]"
             read confirm
             if [ "$confirm" = "y" ]; then
                gcloud secrets create "$2" --replication-policy="automatic" --project="$project"
             fi
             return;
             ;;
      esac
      return
  }';
            $rc_file_content = str_replace("\n{$header_end}", " {$function}\n\n{$header_end}", $rc_file_content);
        } else {
            $this->sendTerminal("     . Found function [$function]");
        }
        //endregion

        return $changes;
    }

    /**
     * Analyze the $rc_file_content
     * @param $config_content
     * @retun boolean|null it will return true if the function has added any new element. false if there are not changes or null if error
     */
    private function verifyConfigVars(&$config_content)
    {
        $changes = false;
        if(!is_array($config_content)) $config_content=[];

        //find development
        $development_key='';
        foreach ($config_content as $key=>$item) if(strpos($key,'development:')===0) $development_key=$key;
        if(!$development_key) $development_key='development:Development in localhost';

        $config_tags = [
            'core.erp.platform_id'=>['title'=>'CF/ERP Platform Id (empty if you do not have any access)']
            ,'core.gcp.project_id'=>['title'=>'DEFAULT PROJECT_ID for GCP']
            ,'core.gcp.datastore.on'=>['title'=>'Activate Datastore access','type'=>'boolean']
            ,'core.gcp.datastore.project_id'=>['title'=>'Default project_id for datastore. Empty if it is the same than core.gcp.project_id','if'=>'core.gcp.datastore.on']
            ,'core.gcp.datastorage.on'=>['title'=>'Activate Storage access','type'=>'boolean']
            ,'core.gcp.datastorage.project_id'=>['title'=>'Default project_id for datastorage. Empty if it is the same than core.gcp.project_id','if'=>'core.gcp.datastorage.on']
            ,'core.gcp.bigquery.on'=>['title'=>'Activate Bigquery access','type'=>'boolean']
            ,'core.gcp.bigquery.project_id'=>['title'=>'Default project_id for bigquery. Empty if it is the same than core.gcp.project_id','if'=>'core.gcp.bigquery.on']
            ,'core.cache.cache_path'=>['title'=>'Development Cache Path','development'=>true]
        ];
        foreach ($config_tags as $config_tag=>$content) {
            //evaluate $content['if']
            if(isset($content['if']) && (!isset($config_content[$content['if']]) || !$config_content[$content['if']])) continue;

            //get current value
            $config_value = $this->core->config->get($config_tag);

            //if $type is boolean convert boolean to string to facilitate the insertion
            if(isset($content['type']) && $content['type']=='boolean') {
                $config_value = ($config_value)?'true':'false';
                $content['allowed_values'] = ['true','false'];
            }
            $new_value = $this->promptVar(["title" => "   # {$config_tag}: {$content['title']}", "default" => $config_value,'allowed_values'=>$content['allowed_values']??null]);
            if($new_value!=$config_value) {
                $changes=true;
                //if $type is boolean convert string to boolean
                if(isset($content['type']) && $content['type']=='boolean') {
                    $config_value = $config_value=='true';
                }

                if((isset($content['development']) && $content['development']) || isset($config_content[$development_key][$config_tag])) $config_content[$development_key][$config_tag] = $new_value;
                else $config_content[$config_tag] = $new_value;
            }
        }

        return $changes;
    }
}