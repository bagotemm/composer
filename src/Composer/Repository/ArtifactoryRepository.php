<?php

namespace Composer\Repository;

use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use Composer\Package\AliasPackage;
use Composer\Package\Version\VersionParser;
use Composer\DependencyResolver\Pool;
use Composer\Json\JsonFile;
use Composer\Cache;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Util\RemoteFilesystem;
use Composer\Package\Loader\ValidatingArrayLoader;

/**
 * @author Effitic
 */
class ArtifactoryRepository extends ArrayRepository
{
	private $url;
	private $user;
	private $pass;
	private $repo;
	private $urlStorage = "/api/storage/";
	private $io;


    /**
     * Initializes Artifactory repository.
     */
    public function __construct(array $config, $io)
    {
        $this->url = $config['url'];
        $this->user = $config['user'];
        $this->pass = $config['pass'];
        $this->repo = $config['repo'];
        $this->io = $io;
    }
    
    /**
     * Initializes repository (read Artifactory remote folder).
     */
    protected function initialize()
    {
        parent::initialize();
        
        $this->readArtifactoryFolder();

    }
    
    private function readArtifactoryFolder() {
    	$loader = new ValidatingArrayLoader(new ArrayLoader, false);
    	$urlList = $this->url."/api/storage/".$this->repo."?list&listFolders=0&includeRootPath=0&deep=1";
    	
    	//Init curl
    	$curlHandle = curl_init($urlList);
    	$curl_post_data = array(
	        'list' => '1',
	        'listFolders' => '0',
	        'includeRootPath' => '1',
	        'deep' => '1'
		);
    	
    	//curl options
    	curl_setopt($curlHandle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    	curl_setopt($curlHandle, CURLOPT_USERPWD, $this->user . ':' . $this->pass);
    	curl_setopt($curlHandle, CURLOPT_SSLVERSION,3);
		curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curlHandle, CURLOPT_HEADER, true);
	 	curl_setopt($curlHandle, CURLOPT_HTTPGET, true);
	 	curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)"); 
    	curl_setopt($curlHandle, CURLOPT_URL, $urlList);	
    	
    	//Execution
    	$resultat = curl_exec($curlHandle);
    	
    	//Get JSON File
    	$jsonFile = substr($resultat, strpos($resultat,'{'));
    	$results = json_decode($jsonFile,true);

    	
    	foreach ($results['files'] as $result) {
    		$distUrl = $result['uri'];
    		$distType = substr($distUrl, strrpos($distUrl,'.',-1)+1);
  	
    		if("pom" == $distType)  {
    			
    			//Get XML data from URI
    			curl_setopt($curlHandle, CURLOPT_URL, $this->url.$this->repo.$distUrl);
    			$xmlFile = curl_exec($curlHandle);
    			$xmlFile = substr($xmlFile, strpos($xmlFile, '<?'));
    			$pom = simplexml_load_string($xmlFile);
    			$pom = $pom->children();
    			
    			//Package construction
    			$package['name'] = $pom->artifactId->__toString();
    			$package['version'] = $pom->version->__toString();
				$package['description'] = $pom->description->__toString();
    			$package['dist']=array(
    					"url" => $this->url.$this->repo.substr($distUrl,0,strlen($distUrl)-3).$pom->packaging->__toString(),
    					"type" => $pom->packaging->__toString());
    		
    			//Load package
    			parent::addPackage($loader->load($package));
    		}

    	}
    	$totalPackages = parent::getPackages();
    	curl_close($curlHandle);
    }
}
