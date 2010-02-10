<?php

/**
 * Sapphire library for handling PDF generation
 */
class PDFGenerator {
	protected $data, $template;
	protected $filename;
	
	function __construct($data, $template, $filename = null) {
		$this->data = $data;
		$this->template = $template;
		$this->filename = $filename;
	}
	
	function generate($filename) {
		$baseFile = preg_replace('/\\.pdf$/','',$filename);
		$htmlFile = "{$baseFile}.html";
		$tidyFile = "{$baseFile}_tidy.html";
		$pdfFile = "$baseFile.pdf";

		$CLI_tidyFile = escapeshellarg($tidyFile);
		$CLI_htmlFile = escapeshellarg($htmlFile);
		$CLI_pdfFile = escapeshellarg($pdfFile);

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
		@exec("tidy -asxhtml -utf8 -output $CLI_tidyFile $CLI_htmlFile", $output, $return);
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

		$CLI_jarFile = escapeshellarg(Director::baseFolder() . '/pdfgeneration/java/css2fopnew1_4_1.jar');

		$response = exec("$javabin -jar $CLI_jarFile  $CLI_tidyFile -pdf $CLI_pdfFile paper-size=a4 &> /dev/stdout", $output, $return);
		if($return > 0) user_error("css2fop failed: " . implode("\n", $output), E_USER_ERROR);
		//print_r($output);
		//die();

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