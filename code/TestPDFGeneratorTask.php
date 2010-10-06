<?php

/**
 * A test of the PDF generator necessitating human-interaction
 */
class TestPDFGeneratorTask extends BuildTask {
	function run($request) {
		
		$pdf = new PDFGenerator(new ArrayData(array()), 'TestPDFGeneratorTask');
		$pdf->generate(BASE_PATH . '/assets/TestPDFGeneratorTask.pdf'); 
		
		
		echo "Done! See generated PDF <a href=\"" . BASE_URL . '/assets/TestPDFGeneratorTask.pdf'
			. "\">here</a>.";
		
	}
}