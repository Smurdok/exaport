<?php
/***************************************************************
 *  Copyright notice
*
*  (c) 2006 exabis internet solutions <info@exabis.at>
*  All rights reserved
*
*  You can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This module is based on the Collaborative Moodle Modules from
*  NCSA Education Division (http://www.ncsa.uiuc.edu)
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once dirname(__FILE__).'/inc.php';

$courseid = optional_param('courseid', 0, PARAM_INT);
$sort = optional_param('sort', '', PARAM_RAW);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$print = optional_param('print', false, PARAM_BOOL);

block_exaport_require_login($courseid);

$context = get_context_instance(CONTEXT_SYSTEM);

if (! $course = $DB->get_record("course", array("id" => $courseid)) ) {
	error("That's an invalid course id");
}

$url = '/blocks/exaport/view_items.php';
$PAGE->set_url($url);

if (!$print)
	block_exaport_print_header("bookmarks");
else {
	?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
<title>Alle Eintr&auml;ge</title>

<meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
<meta content="moodle, Alle Eintr&auml;ge" name="keywords" />
<link
	href="<?php echo $CFG->wwwroot;?>/theme/styles.php/standard/1341490079/all"
	type="text/css" rel="stylesheet" />
<link
	href="<?php echo $CFG->wwwroot;?>/theme/yui_combo.php?3.5.1/build/cssreset/reset.css&3.5.1/build/cssfonts/fonts.css&3.5.1/build/cssgrids/grids.css&3.5.1/build/cssbase/base.css"
	type="text/css" rel="stylesheet" />
<link href="printversion.css" type="text/css" rel="stylesheet" />
<?php 
echo '<link href="'.$CFG->wwwroot.'blocks/exaport/styles.css" type="text/css" rel="stylesheet" />';
echo '</head><body>';
}

echo "<div class='box generalbox'>";
if (block_exaport_course_has_desp()) $pref="desp_";
else $pref="";
echo $OUTPUT->box( text_to_html(get_string($pref."explaining","block_exaport")) , "center");
echo "</div>";

$userpreferences = block_exaport_get_user_preferences();

if (!$sort && $userpreferences && isset($userpreferences->itemsort)) {
	$sort = $userpreferences->itemsort;
}

// check sorting
$parsedsort = block_exaport_parse_item_sort($sort);
$sort = $parsedsort[0].'.'.$parsedsort[1];

$sortkey = $parsedsort[0];

if ($parsedsort[1] == "desc") {
	$newsort = $sortkey.".asc";
} else {
	$newsort = $sortkey.".desc";
}
$sorticon = $parsedsort[1].'.gif';



block_exaport_setup_default_categories();

// read all categories
$categories = $DB->get_records_sql('
	SELECT c.id, c.name, c.pid, COUNT(i.id) AS item_cnt
	FROM {block_exaportcate} c
	LEFT JOIN {block_exaportitem} i ON i.categoryid=c.id
	WHERE c.userid = ?
	GROUP BY c.id
	ORDER BY c.name ASC
', array($USER->id));

// build a tree according to parent
$categoriesByParent = array();
foreach ($categories as $category) {
	if (!isset($categoriesByParent[$category->pid])) $categoriesByParent[$category->pid] = array();
	$categoriesByParent[$category->pid][] = $category;
}

// the main root category
$rootCategory = (object) array(
	'id' => 0,
	'pid' => -999,
	'name' => 'root',
	'item_cnt' => 'todo'
);
$categories[0] = $rootCategory;

// what's the current category? invalid / no category = root
if (isset($categories[$categoryid])) {
	$currentCategory = $categories[$categoryid];
} else {
	$currentCategory = $rootCategory;
}

// what's the parent category?
if (isset($categories[$currentCategory->pid])) {
	$parentCategory = $categories[$currentCategory->pid];
} else {
	$parentCategory = null;
}

// what's the display layout: tiles / details?
$layout = optional_param('layout', '', PARAM_TEXT);
if (!$layout && isset($userpreferences->view_items_layout)) $layout = $userpreferences->view_items_layout;
if ($layout != 'details') $layout = 'tiles'; // default = tiles

// save user preferences
block_exaport_set_user_preferences(array('itemsort'=>$sort, 'view_items_layout'=>$layout));

echo todo_string('current_category').': ';
echo '<select onchange="document.location.href=\''.$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid=\'+this.value;">';
echo '<option value="">'.$rootCategory->name.'</option>';
function block_exaport_print_category_select($categoriesByParent, $currentCategoryid, $pid=0, $parentText='') {
	if (!isset($categoriesByParent[$pid])) return;

	foreach ($categoriesByParent[$pid] as $category) {
		echo '<option value="'.$category->id.'"'.($currentCategoryid == $category->id?' selected="selected"':'').'>';
		echo $parentText.$category->name;
		if ($category->item_cnt) echo ' ('.$category->item_cnt.')';
		echo '</option>';
		block_exaport_print_category_select($categoriesByParent, $currentCategoryid,
			$category->id, $category->name.' &rArr; ');
	}
}
block_exaport_print_category_select($categoriesByParent, $currentCategory->id);
echo '</select>';

echo '<br />';
echo todo_string('layout').': ';
echo '<a href="'.$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid='.$categoryid.'&layout=tiles"
	'.($layout == 'tiles'?' style="font-weight: bold;"':'').'>'.
	todo_string("tiles", "block_exaport")."</a> ";
echo '<a href="'.$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid='.$categoryid.'&layout=details"
	'.($layout == 'details'?' style="font-weight: bold;"':'').'>'.
	todo_string("details", "block_exaport")."</a> ";

echo '<br />';
echo todo_string('new').': ';
echo 'Folder ';
echo '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&sesskey='.sesskey().'&categoryid='.$categoryid.'&type=link">'.
	get_string("link", "block_exaport")."</a> ";
echo '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&sesskey='.sesskey().'&categoryid='.$categoryid.'&type=file">'.
	get_string("file", "block_exaport")."</a> ";
echo '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&sesskey='.sesskey().'&categoryid='.$categoryid.'&type=note">'.
	get_string("note", "block_exaport")."</a> ";

if (!$print) {
	echo "<div class='block_eportfolio_center'>";
}


$sql_sort = block_exaport_item_sort_to_sql($parsedsort);

$condition = array($USER->id, $currentCategory->id);

$items = $DB->get_records_sql("
		SELECT i.*, COUNT(com.id) As comments -- , c.fullname As coursename
		FROM {block_exaportitem} i
		-- LEFT JOIN {course} c on i.courseid = c.id
		LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id
		WHERE i.userid = ? AND i.categoryid=?
		-- sql_type_where
			AND (i.isoez=0 OR (i.isoez=1 AND (i.intro<>'' OR i.url<>'' OR i.attachment<>'')))
		GROUP BY i.id, i.name, i.intro, i.timemodified, i.userid, i.type, i.categoryid, i.url, i.attachment, i.courseid, i.shareall, i.externaccess, i.externcomment, i.sortorder,
		i.isoez, i.fileurl, i.beispiel_url, i.exampid, i.langid, i.beispiel_angabe, i.source, i.sourceid, i.iseditable
		$sql_sort
		-- coursename, 
	", $condition);

if ($items || !empty($categoriesByParent[$currentCategory->id]) || $parentCategory) {
	// show output only if we have items, or we have subcategories, or we are in a subcategory

	$table = new html_table();
	$table->width = "100%";

	$table->head = array();
	$table->size = array();

	$table->head['type'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&amp;categoryid=$categoryid&amp;sort=".
			($sortkey == 'type' ? $newsort : 'type') ."'>" . get_string("type", "block_exaport") . "</a>";
	$table->size['type'] = "14";

	$table->head['name'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&amp;categoryid=$categoryid&amp;sort=".
			($sortkey == 'name' ? $newsort : 'name') ."'>" . get_string("name", "block_exaport") . "</a>";
	$table->size['name'] = "30";

	$table->head['date'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&amp;categoryid=$categoryid&amp;sort=".
			($sortkey == 'date' ? $newsort : 'date.desc') ."'>" . get_string("date", "block_exaport") . "</a>";
	$table->size['date'] = "20";

	// $table->head[] = get_string("course","block_exaport");
	// $table->size[] = "14";

	$table->head[] = get_string("comments","block_exaport");
	$table->size[] = "8";

	$table->head[] = '';
	$table->size[] = "10";

	// add arrow to heading if available
	if (isset($table->head[$sortkey]))
		$table->head[$sortkey] .= "<img src=\"pix/$sorticon\" alt='".get_string("updownarrow", "block_exaport")."' />";


	$table->data = Array();
	$lastcat = "";
	
	$item_i = -1;

	if ($parentCategory) {
		// if isn't parent category, show link to go to parent category
		$item_i++;
		$table->data[$item_i] = array();
		$table->data[$item_i]['type'] = 'folder';
		$table->data[$item_i]['name'] = 
			'<a href="'.$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid='.$parentCategory->id.'">parent: '.$parentCategory->name.'</a>';
	}
	
	if (!empty($categoriesByParent[$currentCategory->id])) {
		foreach ($categoriesByParent[$currentCategory->id] as $category) {
			$item_i++;
			$table->data[$item_i] = array();
			$table->data[$item_i]['type'] = 'folder';
			$table->data[$item_i]['name'] = 
				'<a href="'.$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid='.$category->id.'">'.$category->name.'</a>';
		}
	}

	$itemscnt = count($items);
	foreach ($items as $item) {
		$item_i++;

		$table->data[$item_i] = array();

		$table->data[$item_i]['type'] = get_string($item->type, "block_exaport");

		$table->data[$item_i]['name'] = "<a href=\"".s("{$CFG->wwwroot}/blocks/exaport/shared_item.php?courseid=$courseid&access=portfolio/id/".$USER->id."&itemid=$item->id&backtype=".$type."&att=".$item->attachment)."\">" . $item->name . "</a>";
		if ($item->intro) {
			$intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', get_context_instance(CONTEXT_USER, $item->userid)->id, 'block_exaport', 'item_content', 'portfolio/id/'.$item->userid.'/itemid/'.$item->id);

			$shortIntro = substr(trim(strip_tags($intro)), 0, 20);
			if(preg_match_all('#(?:<iframe[^>]*)(?:(?:/>)|(?:>.*?</iframe>))#i', $intro, $matches)) {
				$shortIntro = $matches[0][0];
			}

			if (!$intro) {
				// no intro
			} elseif ($print) {
				// show whole intro for printing
				$table->data[$item_i]['name'] .= "<table width=\"50%\"><tr><td width=\"50px\">".format_text($intro, FORMAT_HTML)."</td></tr></table>";
			} elseif ($shortIntro == $intro) {
				// very short one
				$table->data[$item_i]['name'] .= "<table width=\"50%\"><tr><td width=\"50px\">".format_text($intro, FORMAT_HTML)."</td></tr></table>";
			} else {
				// display show/hide buttons
				$table->data[$item_i]['name'] .=
				'<div><div id="short-preview-'.$item_i.'"><div>'.$shortIntro.'...</div>
				<a href="javascript:long_preview_show('.$item_i.')">['.get_string('more').'...]</a>
				</div>
				<div id="long-preview-'.$item_i.'" style="display: none;"><div>'.$intro.'</div>
				<a href="javascript:long_preview_hide('.$item_i.')">['.strtolower(get_string('hide')).'...]</a>
				</div>';
			}
		}

		$table->data[$item_i]['date'] = userdate($item->timemodified);
		// $table->data[$item_i]['course'] = $item->coursename;
		$table->data[$item_i]['comments'] = $item->comments;

		$icons = '';
		
		$comp = block_exaport_check_competence_interaction();
		
		if($comp){
			$array = block_exaport_get_competences($item, 0);
		
			//if item is assoziated with competences display them
			if(count($array)>0){
				$competences = "";
				foreach($array as $element){
		
					$conditions = array("id" => $element->descid);
					$competencesdb = $DB->get_record('block_exacompdescriptors', $conditions, $fields='*', $strictness=IGNORE_MISSING); 

					if($competencesdb != null){
						$competences .= $competencesdb->title.'<br>';
					}
				}
				$competences = str_replace("\r", "", $competences);
				$competences = str_replace("\n", "", $competences);
				$competences = str_replace("\"", "&quot;", $competences);
				$competences = str_replace("'", "&prime;", $competences);
				
				$icons .= '<script type="text/javascript" src="lib/wz_tooltip.js"></script><a onmouseover="Tip(\''.$competences.'\')" onmouseout="UnTip()"><img src="'.$CFG->wwwroot.'/pix/t/grades.gif" class="iconsmall" alt="'.'competences'.'" /></a>';
			}
		}
		
		$icons .= '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&amp;id='.$item->id.'&amp;sesskey='.sesskey().'&amp;action=edit&amp;backtype='.$type.'"><img src="'.$CFG->wwwroot.'/pix/t/edit.gif" class="iconsmall" alt="'.get_string("edit").'" /></a> ';

		$icons .= '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&amp;id='.$item->id.'&amp;sesskey='.sesskey().'&amp;action=delete&amp;confirm=1&amp;backtype='.$type.'"><img src="'.$CFG->wwwroot.'/pix/t/delete.gif" class="iconsmall" alt="' . get_string("delete"). '"/></a> ';

		if (block_exaport_feature_enabled('share_item')) {
			if (has_capability('block/exaport:shareintern', $context)) {
				if( ($item->shareall == 1) ||
						($item->externaccess == 1) ||
						(($item->shareall == 0) && (count_records('block_exaportitemshar', 'itemid', $item->id, 'original', $USER->id) > 0))) {
					$icons .= '<a href="'.$CFG->wwwroot.'/blocks/exaport/share_item.php?courseid='.$courseid.'&amp;itemid='.$item->id.'&backtype='.$type.'">'.get_string("strunshare", "block_exaport").'</a> ';
				}
				else {
					$icons .= '<a href="'.$CFG->wwwroot.'/blocks/exaport/share_item.php?courseid='.$courseid.'&amp;itemid='.$item->id.'&backtype='.$type.'">'.get_string("strshare", "block_exaport").'</a> ';
				}
			}
		}

		// copy files to course
		if ($item->type == 'file' && block_exaport_feature_enabled('copy_to_course'))
			$icons .= '<a href="'.$CFG->wwwroot.'/blocks/exaport/copy_item_to_course.php?courseid='.$courseid.'&amp;itemid='.$item->id.'&backtype='.$type.'">'.get_string("copyitemtocourse", "block_exaport").'</a> ';

		$table->data[$item_i]['icons'] = $icons;
	}

	if ($layout == 'details') {
		echo html_writer::table($table);
	} else {
		foreach ($table->data as $item) {
			echo '<div>'.$item['name'].'</div>';
		}
	}
} else {
	echo block_exaport_get_string("nobookmarks".$type,"block_exaport");
}

// deactivate for now
/*
if(!$print) {
	echo "<div class='block_eportfolio_center'>";
	echo "<a target='_blank' href='".$CFG->wwwroot.$url."?courseid=".$courseid."&print=true'>".get_string('printerfriendly', 'group')."</a>";
	echo "</div>";
}
*/

if (!$print) 
	echo $OUTPUT->footer($course);
else
	echo '</body></html>';
