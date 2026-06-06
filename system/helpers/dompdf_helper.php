<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

if (!function_exists('dompdf_record_warnings_wrapper')) {
	function dompdf_record_warnings_wrapper($errno, $errstr, $errfile, $errline)
	{
		if ($errno & (E_DEPRECATED | E_USER_DEPRECATED)) {
			return true;
		}
		if (function_exists('record_warnings')) {
			return record_warnings($errno, $errstr, $errfile, $errline);
		}
		return false;
	}
}

function pdf_create($html, $filename='', $stream=TRUE, $papersize = "A4", $orientation = "portrait") 
{
    require_once("dompdf/dompdf_config.inc.php");

    $oldLevel = error_reporting();
    error_reporting($oldLevel & ~E_DEPRECATED & ~E_USER_DEPRECATED);

	set_error_handler('dompdf_record_warnings_wrapper');

    $dompdf = new DOMPDF();
    $dompdf->load_html($html);
    $dompdf->set_paper($papersize, $orientation );
    $dompdf->render();
    if ($stream) {
        $dompdf->stream($filename.".pdf");
    } else {
        $out = $dompdf->output();
		restore_error_handler();
        error_reporting($oldLevel);
        return $out;
    }

	restore_error_handler();
    error_reporting($oldLevel);
}
?>