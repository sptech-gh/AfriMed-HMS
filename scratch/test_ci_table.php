<?php
// Mock or load CI Table class
class Mock_Table {
    public $heading = array();
    public $rows = array();
    public $template = NULL;
    public $empty_cells = '';

    public function set_template($template) {
        $this->template = $template;
    }
    public function set_heading() {
        $this->heading = func_get_args();
    }
    public function set_empty($value) {
        $this->empty_cells = $value;
    }
    public function add_row() {
        $this->rows[] = func_get_args();
    }
    public function generate() {
        // Simple representation of CI Table generate
        $html = $this->template['table_open'] ?? '<table>';
        $html .= '<thead><tr>';
        foreach ($this->heading as $h) {
            $html .= '<th>' . $h . '</th>';
        }
        $html .= '</tr></thead>';
        $html .= '<tbody>';
        foreach ($this->rows as $r) {
            $html .= '<tr>';
            foreach ($r as $c) {
                $html .= '<td>' . $c . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }
}

// Let's run with the exact settings from the controller
$table = new Mock_Table();
$tmpl = array(
    'table_open'    => '<table class="table table-hover table-striped opd-clickable-table">',
    'row_start'     => '<tr class="opd-row">',
    'row_alt_start' => '<tr class="opd-row">',
);
$table->set_template($tmpl);
$table->set_empty('&nbsp;');
$table->set_heading('OPD No', 'Patient No', 'Patient Name', 'Age', 'Coverage', 'Visit Type', 'Visit Date Time', 'Department', 'Consultant Doctor', 'Status', '');

// No rows added
echo "Generated HTML:\n";
echo $table->generate() . "\n";
?>
