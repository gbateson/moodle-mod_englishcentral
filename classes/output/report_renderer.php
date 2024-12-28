<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/06/26
 * Time: 13:16
 */

namespace mod_englishcentral\output;

use mod_englishcentral\constants;
use mod_englishcentral\utils;

class report_renderer extends \plugin_renderer_base {

    public function render_reportmenu($moduleinstance, $cm, $dayslimit, $format) {
        $reports = [];
        $theurl = new \moodle_url(constants::M_URL . '/reports.php',
        ['id' => $cm->id, 'n' => $moduleinstance->id, 'dayslimit' => $dayslimit, 'format' => $format]);
/*
        $basic = new \single_button(
                new \moodle_url(constants::M_URL . '/reports.php',
                        ['report' => 'basic', 'id' => $cm->id, 'n' => $moduleinstance->id]),
                get_string('basicreport', constants::M_COMPONENT), 'get');
        $reports[] = ['button' => $this->render($basic),
        'text' => get_string('attemptssummary_explanation', constants::M_COMPONENT)];
*/
        $theurl->param('report', 'attemptssummary');
        $graphicalattempts = new \single_button(
            $theurl,
            get_string('attemptssummaryreport', constants::M_COMPONENT), 'get');
        $reports[] = ['button' => $this->render($graphicalattempts),
        'text' => get_string('attemptssummaryreport_explanation', constants::M_COMPONENT)];

        $theurl->param('report', 'attempts');
        $attempts = new \single_button(
                $theurl,
                get_string('attemptsreport', constants::M_COMPONENT), 'get');
        $reports[] = ['button' => $this->render($attempts),
            'text' => get_string('attempts_explanation', constants::M_COMPONENT)];

        $theurl->param('report', 'videoperformance');
        $videoperformance = new \single_button(
            $theurl,
            get_string('videoperformancereport', constants::M_COMPONENT), 'get');
        $reports[] = ['button' => $this->render($videoperformance),
            'text' => get_string('videoperformance_explanation', constants::M_COMPONENT)];

        $theurl->param('report', 'courseattempts');
        $courseattempts = new \single_button(
            $theurl,
            get_string('courseattemptsreport', constants::M_COMPONENT), 'get');
        $reports[] = ['button' => $this->render($courseattempts),
            'text' => get_string('courseattempts_explanation', constants::M_COMPONENT)];

        $data = ['reports' => $reports];
        $ret = $this->render_from_template('mod_englishcentral/reportsmenu', $data);

        return $ret;
    }

    public function render_delete_allattempts($cm) {
        $deleteallbutton = new \single_button(
                new \moodle_url(constants::M_URL . '/manageattempts.php', ['id' => $cm->id, 'action' => 'confirmdeleteall']),
                get_string('deleteallattempts', constants::M_COMPONENT), 'get');
        $ret = \html_writer::div($this->render($deleteallbutton), constants::M_CLASS . '_actionbuttons');
        return $ret;
    }

    public function render_reporttitle_html($course, $username) {
        $ret = $this->output->heading(format_string($course->fullname), 2);
        $ret .= $this->output->heading(get_string('reporttitle', constants::M_COMPONENT, $username), 3);
        return $ret;
    }

    public function render_empty_section_html() {
        global $CFG;
        return $this->output->heading(get_string('nodataavailable', constants::M_COMPONENT), 3);
    }

    public function render_exportbuttons_html($cm, $formdata, $showreport) {
        // convert formdata to array
        $formdata = (array) $formdata;
        $formdata['id'] = $cm->id;
        $formdata['report'] = $showreport;
        $formdata['format'] = 'csv';
        $excel = new \single_button(
                new \moodle_url(constants::M_URL . '/reports.php', $formdata),
                get_string('exportexcel', constants::M_COMPONENT), 'get');

        return \html_writer::div($this->render($excel), constants::M_CLASS . '_actionbuttons');
    }

    public function render_grading_exportbuttons_html($cm, $formdata, $action) {
        // convert formdata to array
        $formdata = (array) $formdata;
        $formdata['id'] = $cm->id;
        $formdata['action'] = $action;
        $formdata['format'] = 'csv';
        $excel = new \single_button(
                new \moodle_url(constants::M_URL . '/grading.php', $formdata),
                get_string('exportexcel', constants::M_COMPONENT), 'get');

        return \html_writer::div($this->render($excel), constants::M_CLASS . '_actionbuttons');
    }

    public function render_report_csv($sectiontitle, $report, $head, $rows, $fields) {

        // Use the sectiontitle as the file name. Clean it and change any non-filename characters to '_'.
        $name = clean_param($sectiontitle, PARAM_FILE);
        $name = preg_replace("/[^A-Z0-9]+/i", "_", utils::super_trim($name));
        $quote = '"';
        $delim = ",";// "\t";
        $newline = "\r\n";

        header("Content-Disposition: attachment; filename=$name.csv");
        header("Content-Type: text/comma-separated-values");

        // echo header
        $heading = "";
        foreach ($head as $headfield) {
            $heading .= $quote . $headfield . $quote . $delim;
        }
        echo $heading . $newline;

        // echo data rows
        foreach ($rows as $row) {
            $datarow = "";
            foreach ($fields as $field) {
                $datarow .= $quote . $row->{$field} . $quote . $delim;
            }
            echo $datarow . $newline;
        }
        exit();
    }

    public function render_report_tabular($report, $head, $rows, $fields) {
        global $CFG;
        if (empty($rows)) {
            return $this->render_empty_section_html();
        }

        // set up our table and head attributes
        $tableattributes = ['class' => 'generaltable ' . constants::M_CLASS . '_table'];

        $htmltable = new \html_table();
        $tableid = \html_writer::random_id(constants::M_COMPONENT);
        $htmltable->id = $tableid;
        $htmltable->attributes = $tableattributes;

        $headcells = [];
        foreach ($head as $headcell) {
            $headcells[] = new \html_table_cell($headcell);
        }
        $htmltable->head = $head;

        foreach ($rows as $row) {
            $htr = new \html_table_row();
            // set up descrption cell
            $cells = [];
            foreach ($fields as $field) {
                $cell = new \html_table_cell($row->{$field});
                $cell->attributes = ['class' => constants::M_CLASS . '_cell_' . $report . '_' . $field];
                $htr->cells[] = $cell;
            }

            $htmltable->data[] = $htr;
        }

        $html = \html_writer::table($htmltable);

        // if datatables set up datatables
        if(constants::M_USE_DATATABLES) {
            $dtlang = [];
            $dtlang['search'] = get_string('datatables_search', constants::M_COMPONENT);
            $dtlang['emptyTable'] = get_string('datatables_emptytable', constants::M_COMPONENT);
            $dtlang['zeroRecords'] = get_string('datatables_zerorecords', constants::M_COMPONENT);
            $dtlang['paginate'] = [];
            $dtlang['paginate']['first'] = get_string('datatables_paginate_first', constants::M_COMPONENT);
            $dtlang['paginate']['last'] = get_string('datatables_paginate_last', constants::M_COMPONENT);
            $dtlang['paginate']['next'] = get_string('datatables_paginate_next', constants::M_COMPONENT);
            $dtlang['paginate']['previous'] = get_string('datatables_paginate_previous', constants::M_COMPONENT);
            $dtlang['aria'] = [];
            $dtlang['aria']['sortAscending'] = get_string('datatables_aria_sortascending', constants::M_COMPONENT);
            $dtlang['aria']['sortDescending'] = get_string('datatables_aria_sortdescending', constants::M_COMPONENT);
            $dtlang['info'] = get_string('datatables_info', constants::M_COMPONENT);
            $dtlang['infoEmpty'] = get_string('datatables_infoempty', constants::M_COMPONENT);
            $dtlang['lengthMenu'] = get_string('datatables_lengthmenu', constants::M_COMPONENT);

            $tableprops = [];
            $tableprops['paging'] = true;
            $tableprops['pageLength'] = 10;
            $tableprops['language'] = $dtlang;
            $opts = [];
            $opts['tableid'] = $tableid;
            $opts['tableprops'] = $tableprops;
            $this->page->requires->js_call_amd(constants::M_COMPONENT . "/datatables", 'init', [$opts]);
        }
        return $html;

    }

    function show_reports_footer($moduleinstance, $cm, $formdata, $showreport) {
        // a return to reports top link
        $link = new \moodle_url(constants::M_URL . '/reports.php',
                ['report' => 'menu', 'id' => $cm->id, 'n' => $moduleinstance->id, 'dayslimit' => $formdata->dayslimit, 'format' => $formdata->format]);
        $ret = \html_writer::link($link, get_string('returntoreports', constants::M_COMPONENT),['class'=>'mod_ec_returntoreports']);
        $ret .= $this->render_exportbuttons_html($cm, $formdata, $showreport);
        return $ret;
    }

    function show_perpage_selector($url, $paging) {
        $options = ['5' => 5, '10' => 10, '20' => 20, '40' => 40, '80' => 80, '150' => 150];
        $selector = new \single_select($url, 'perpage', $options, $paging->perpage);
        $selector->set_label(get_string('attemptsperpage', constants::M_COMPONENT));
        return $this->render($selector);
    }

    function show_user_report_options($url, $currentdayslimit, $currentformat) {
        $dayslimitselector = $this->fetch_dayslimit_selector($url, $currentdayslimit);
        $formatselector = $this->fetch_format_selector($url, $currentformat);
        return \html_writer::div($formatselector . $dayslimitselector  , 'mod_ec_user_report_opts float-right');
    }

    function fetch_dayslimit_selector($url, $currentselection) {
        $options = ['0' => get_string('nodayslimit', constants::M_COMPONENT),
            '7' => get_string('xdayslimit', constants::M_COMPONENT, 7),
            '14' => get_string('xdayslimit', constants::M_COMPONENT, 14),
            '30' => get_string('xdayslimit', constants::M_COMPONENT, 30),
            '90' => get_string('xdayslimit', constants::M_COMPONENT, 90),
            '180' => get_string('xdayslimit', constants::M_COMPONENT, 180),
            '365' => get_string('xdayslimit', constants::M_COMPONENT, 365)];
        $theurl = clone $url;
        $theurl->remove_params('dayslimit');
        $selector = new \single_select($theurl, 'dayslimit', $options, $currentselection);
        $widget = $this->render($selector);
        return $widget;
    }

    function fetch_format_selector($url, $currentselection) {
        $params = [];
        $theurl = clone $url;

        $theurl->param('format', 'tabular');
        $params['tableurl'] = $theurl->out();

        $theurl->param('format', 'graphical');
        $params['charturl'] = $theurl->out();

        $theurl->param('format', 'combined');
        $params['combiurl'] = $theurl->out();

        switch($currentselection){
            case "tabular":
                $params['istable'] = true;
                break;
            case "graphical":
                $params['ischart'] = true;
                break;
            case "combined":
            default:
                $params['iscombi'] = true; break;
        }

        return  $this->render_from_template('mod_englishcentral/reportformatselector', $params);
    }

    /**
     * Returns HTML to display a single paging bar to provide access to other pages  (usually in a search)
     *
     * @param int $totalcount The total number of entries available to be paged through
     * @param stdclass $paging an object containting sort/perpage/pageno fields. Created in reports.php and grading.php
     * @param string|moodle_url $baseurl url of the current page, the $pagevar parameter is added
     * @return string the HTML to output.
     */
    function show_paging_bar($totalcount, $paging, $baseurl) {
        $pagevar = "pageno";
        // add paging params to url (NOT pageno)
        $baseurl->params(['perpage' => $paging->perpage, 'sort' => $paging->sort]);
        return $this->output->paging_bar($totalcount, $paging->pageno, $paging->perpage, $baseurl, $pagevar);
    }

    function show_export_buttons($cm, $formdata, $showreport) {
        switch ($showreport) {
            case 'grading':
                return $this->render_grading_exportbuttons_html($cm, $formdata, $showreport);
            default:
                return $this->render_exportbuttons_html($cm, $formdata, $showreport);
        }
    }

}
