<?php

/**
 * Sapphire library for handling PDF generation
 */
class PDFGenerator {
	protected $data, $template;
	protected $filename;
	
	protected $fontDir = 'pdfgeneration/fonts';

	/**
	 * Keep track of the generated config file
	 */
	private $configFile = null;
	
	function __construct($data, $template, $filename = null) {
		$this->data = $data;
		$this->template = $template;
		$this->filename = $filename;
	}
	
	/**
	 * Specify a directory containing fonts to be used in PDF generation
	 * If the directory starts with a "/", it will be interpreted as absolute, otherwise relative
	 * to the 
	 */
	function setFontDir($fontDir) {
		$this->fontDir = $fontDir;
		$this->cleanupConfigFile();
	}

	/**
	 * Generate a config file, if needed, and return its name
	 */
	function configFile() {
		if(!$this->fontDir) return null;
		
		if(!$this->configFile) {
			$this->configFile = tempnam(TEMP_FOLDER, 'fop-config-');

			// Absoluteize fontDir
			$fontDir = $this->fontDir[0] == '/' ? $this->fontDir : BASE_PATH . '/' . $this->fontDir;
			
			$fontReferences = "";
			foreach(scandir($fontDir) as $file) {
				if(preg_match('/^(.*)[ -]?(Bold|Italic|Bold ?Italic|)\.ttf/Ui', $file, $matches)) {
					$fontSuffix = strtolower($matches[2]);
					$fontName = $matches[1];
					$fontStyle = strpos($fontSuffix,'italic') === false ? 'normal' : 'italic';
					$fontWeight = strpos($fontSuffix,'bold') === false ? '400' : '500';
					
					$fontReferences .= "<font embed-url=\"$file\"><font-triplet name=\"$fontName\" style=\"$fontStyle\" weight=\"$fontWeight\"/></font>\n";
				}
			}

			$config = <<<XML
<fop version="1.0">
	<font-base>$fontDir</font-base>
	<renderers>
	    <renderer mime="application/pdf">
			<fonts>
				<directory>$fontDir</directory>
				$fontReferences
			</fonts>
		</renderer>
	</renderers>
</fop>
XML;
			file_put_contents($this->configFile, $config);
		}	
		return $this->configFile;
	}
	function cleanupConfigFile() {
		if($this->configFile) {
			if(file_exists($this->configFile)) unlink($this->configFile);
			$this->configFile = null;
		}
	}
	
	function generate($filename) {
		$baseFile = preg_replace('/\\.pdf$/','',$filename);
		$htmlFile = "{$baseFile}.html";
		$tidyFile = "{$baseFile}_tidy.html";
		$pdfFile = "$baseFile.pdf";

		$CLI_tidyFile = escapeshellarg($tidyFile);
		$CLI_htmlFile = escapeshellarg($htmlFile);
		$CLI_pdfFile = escapeshellarg($pdfFile);
		$CLI_foFile = escapeshellarg("$baseFile.fo");

		// Render content via template
		SSViewer::setOption('rewriteHashlinks', false);
		$content = $this->data->renderWith($this->template);
		$content = $this->removeuni($content);
		SSViewer::setOption('rewriteHashlinks', true);

		// Write content to file
		$fh = fopen($htmlFile, "w+") or user_error("Couldn't open $baseFile.html for writing", E_USER_ERROR);
		fwrite($fh, $content) or user_error("Couldn't write content to $baseFile.html", E_USER_ERROR);
		fclose($fh);
		
		// Tidy it
		@exec("tidy -asxhtml -utf8 -output $CLI_tidyFile $CLI_htmlFile &> /dev/null", $output, $return);
		//if($return > 0) user_error("Tidy failed: " . implode("\n", $output), E_USER_ERROR);
		
		// Strip unicode
		$tidyContent = file_get_contents($tidyFile);
		$tidyContent = $this->removeuni($tidyContent);
		$fh = fopen($tidyFile, "w");
		fwrite($fh, $tidyContent);
		fclose($fh);
			
		// Special binary selection for manu
        if(file_exists("/usr/share/java/bin/java")) $javabin = "/usr/share/java/bin/java";
        else $javabin = "java";

		$CLI_jarFile = escapeshellarg(Director::baseFolder() . '/pdfgeneration/java/css2xslfo1_6_1.jar');
		$CLI_configFile = escapeshellarg($this->configFile());
		
		$command = "$javabin -jar $CLI_jarFile $CLI_tidyFile -fo $CLI_foFile paper-size=a4  &> /dev/stdout";

		$output = array();
		$response = exec($command, $output, $return);
		array_unshift($output, $command);

		if($return > 0) user_error("css2fop failed: " . implode("\n", $output), E_USER_ERROR);

		$origDir = getcwd();
		chdir('../pdfgeneration/java');
		$command = "$javabin -jar fop.jar -c $CLI_configFile -fo $CLI_foFile -pdf $CLI_pdfFile  &> /dev/stdout";

		$output = array();
		$response = exec($command, $output, $return);
		chdir($origDir);
		array_unshift($output, $command);

		$this->cleanupConfigFile();
		
		if(!file_exists($pdfFile)) throw new Exception("css2fop couldn't create $pdfFile:\n" . implode("<br>\n", $output));
		if(filesize($pdfFile) < 100) throw new Exception("css2fop created a very small (and probably broken) $pdfFile:\n" . implode("\n", $output));

		return file_exists($pdfFile);
	}
	
	function sendToBrowser() {
		if(!file_exists("../assets/.private")) mkdir("../assets/.private");
		$filename = "../assets/.private/contract.pdf";
		$this->generate($filename);

		$response = SS_HTTPRequest::send_file(file_get_contents($filename), basename($filename), 'application/pdf');
		$response->output();
	}

	function removeuni($content){
		preg_match_all("/[\x{90}-\x{3000}]/u", $content, $matches);
		foreach($matches[0] as $match){
			$content = str_replace($match, mb_convert_encoding($match, "HTML-ENTITIES","UTF-8"), $content);
		}
		return $content;
	}
	
}

?>