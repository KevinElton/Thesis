<?php
// Dynamically calculate base path relative to project root
$_basePath = '';
$_scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
$_docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$_projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$_currentDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
$_depth = substr_count(str_replace($_projectRoot, '', $_currentDir), '/');
$_basePath = str_repeat('../', $_depth);
if ($_basePath === '') $_basePath = './';
?>
<!-- Local TailwindCSS and Lucide Icons -->
<script src="<?= $_basePath ?>assets/js/tailwind.js"></script>
<script src="<?= $_basePath ?>assets/js/lucide.min.js"></script>


