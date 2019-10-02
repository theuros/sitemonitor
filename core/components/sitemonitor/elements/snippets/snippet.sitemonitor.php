<?php
$sm = $modx->getService('sitemonitor','Sitemonitor',$modx->getOption('sitemonitor.core_path',null,$modx->getOption('core_path').'components/sitemonitor/'),$scriptProperties);
if (!($sm instanceof Sitemonitor)) return 'ERROR';

/*
$sm->setTpl($modx->getOption('tpl',$scriptProperties,'smTpl'));
$sm->setTplRow($modx->getOption('rowTpl',$scriptProperties,'smTplRow'));
$sm->setTplExt($modx->getOption('tplExt',$scriptProperties,'smTplExt'));
*/

if($tpl) 		$sm->tpl = $tpl;
if($tplRow) 	$sm->tplRow = $tplRow;
if($tplExtRow) 	$sm->tplExtRow = $tplExtRow;

if($modx->getOption('key',$scriptProperties)) $sm->setKey($modx->getOption('key',$scriptProperties));

if($sites){
	$rows = '';
	$sites = explode(",",$sites);
	foreach($sites as $url){
		$rows .= $sm->readRow($url);
	}

	if($tpl)
		$o = $modx->getChunk($tpl,['rows'=>$rows]);
	else 			
		$o = $sm->getChunk('sm_tpl',['rows'=>$rows]);

} else {
	$o = $sm->getData($modx->getOption('add',$scriptProperties));
}

return $o;