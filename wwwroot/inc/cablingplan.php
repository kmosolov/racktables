<?php
/*

Copyright 2011 Dennis Plöger

Copyright 2013 Alexey Barabanov


This is a RackTables extension to display a schematic cabling plan of all
objects in the system. The current revision consists of:

This extension requires an installed PEAR Image-GraphViz module.

*/

require_once 'Image/GraphViz.php';

if (isset ($indexlayout))
	array_push ($indexlayout[2], 'cablingplan');

$page['cablingplan']['title'] = 'Cabling plan';
$page['cablingplan']['parent'] = 'index';
$tab['cablingplan']['default'] = 'Cabling plan (PNG)';
$tab['cablingplan']['defaultgroup'] = 'Cabling plan (Network group)';
$tab['cablingplan']['defaultsvg'] = 'Cabling plan (SVG)';
$tabhandler['cablingplan']['default'] = 'showCablingPlan';
$tabhandler['cablingplan']['defaultgroup'] = 'showCablingPlanGroup';
$tabhandler['cablingplan']['defaultsvg'] = 'showCablingPlanSvg';

$image['cablingplan']['path'] = 'pix/mainmenu/cablingplan.png';
$image['cablingplan']['width'] = 128;
$image['cablingplan']['height'] = 128;

function showCablingPlan()
{
	// Show cabling plan image
	echo "<img hspace='15' vspace='15' src='?module=rendercablingplan&format=png' />\n";
}

function showCablingPlanGroup()
{
    // Show cabling plan image
    echo "<img hspace='5' vspace='5' src='?module=rendercablingplan&format=png&grouping=1' />\n";
}

function showCablingPlanSvg()
{
	// Show cabling plan image
	echo "<img hspace='10' vspace='10' src='?module=rendercablingplan&format=svg' />\n";
}



function renderCablingPlan()
{
	// Build cabling plan

	// Select edges
	$sql = "SELECT oa.id AS source, ob.id AS target, CONCAT(pa.name, _utf8' - ', pb.name) AS label, 0 AS weight " .
		"FROM ((Link l JOIN Port pa ON l.porta = pa.id) JOIN RackObject oa ON pa.object_id = oa.id " .
		"JOIN Port pb ON l.portb = pb.id JOIN RackObject ob ON pb.object_id = ob.id)";

	$result = usePreparedSelectBlade ($sql);
	$edges = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$found = FALSE;
		foreach ($edges as $key => $edge)
			if (($edge['source'] == $row['source']) && ($edge['target'] == $row['target']))
			{
				// Edge already exists ("Parallel"). Alter label and add weight
				$edges[$key]['label'] .= "\n" . $row['label'];
				$edges[$key]['weight']++;
				$found = TRUE;
			}
		if (! $found)
			$edges[] = $row;
	}

	// Select nodes
	$sql = "SELECT DISTINCT o.id AS id, o.name AS label, '' AS url " .
		"FROM Port p JOIN RackObject o ON p.object_id = o.id " .
		"WHERE (p.id IN (SELECT Link.porta AS porta FROM Link) OR p.id IN " .
		"(SELECT Link.portb AS portb FROM Link))";
	$sql = "SELECT DISTINCT o.id AS id, o.name AS label, concat('/index.php?page=object&tab=default&object_id=3',o.id) AS url,
		o.objtype_id as type
        FROM Port p JOIN RackObject o ON p.object_id = o.id
        WHERE (p.id IN (SELECT Link.porta AS porta FROM Link) OR p.id IN
        (SELECT Link.portb AS portb FROM Link));";

	$result = usePreparedSelectBlade ($sql);
	$nodes = $result->fetchAll (PDO::FETCH_ASSOC);

	$tetle="Cabling Plan";
	$graph = new Image_GraphViz(NULL, NULL, $title);
	$graph->addAttributes(array ('label' => $title, 'labelloc' => 't', 'rankdir' => 'LR', splines=>polyline));
	
	$grouping=$_REQUEST['grouping'];	
	if ($grouping) $graph->addCluster
	(
		'cluster_8',
		'Network',
		array( 'URL' => "index.php", 'fontcolor'=>'black', 
				'fillcolor'=>'#eeeeee',
				'rankdir' =>'G',
				'style' => 'filled' ) 
	);

	foreach ($nodes as $node) {
			$cluster='';
		if ($node[type]==7 || $node[type]==8) {
			$fillcolor='#ddddff';
			$cluster='cluster_8';
		}
		else {
			$fillcolor='#ffeeff';
		}
		$graph->addNode
		(
			$node['id'],
			array
			(
				'label' => $node['label'],
				'URL' => "index.php",//$node['url'],
				'fontsize' => 9.0,
				'shape' => 'box',
				'height' => 0.1,
				'style'=>'filled',
				'fillcolor'=>$fillcolor,
				'rank' => $node['type']
			)
			,$cluster
		);
	}

	foreach ($edges as $edge)
		$graph->addEdge
		(
			array ($edge['source'] => $edge['target']),
			array
			(
				'label' => $edge['label'],
				'weight' => floatval ($edge['weight']),
				'color'=>'#aaaaaa',
				'fontsize' => 7.0,
				'arrowhead' => 'dot',
				'arrowtail' => 'dot',
				'arrowsize' => 0.5
			)
		);

	if (in_array ($_REQUEST['format'], array ('svg', 'png')))
		$graph->image ($_REQUEST['format'],'dot');
}
