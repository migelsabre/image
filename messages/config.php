<?php
/**
 * This is the configuration for generating message translations
 * for the Yii framework. It is used by the 'yiic message' command.
 */
return array(
	'sourcePath' => dirname(__FILE__).'/../',
	'messagePath'=>dirname(__FILE__).'/',
	'languages'=>array('ru'),
	'fileTypes'=>array('php'),
	'exclude'=>array(
		'.svn',
		'.git',
	),
);