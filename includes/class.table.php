<?php

/* Builds an HTML table. */
class Table {
	/* An array of headings for each column */
	private $columns = array();
	
	/* A multidimensional array of data, grouped by row. */
	private $rows = array();
	
	/* The number of rows. (count() isn't used because we occasionally need to inflate this number) */
	public $row_count = 0;
	
	/**
	 * CSS classes to be applied to every cell in a particular column number, as specified by the key.
	 * The (numerical) keys are preserved from the input array.
	 */
	private $column_classes = array();
	
	/* CSS classes to be applied to a particular row, as specified by the key. */
	private $row_classes = array();
	
	/* Sets the content of each <th> cell, and optionally the primary column. */
	public function __construct($columns, $primary = null) {
		$this->columns = (array) $columns;
		
		if(isset($primary)) {
			/* All secondary columns will be shrunk to make room for the primary column. */
			unset($columns[$primary]);
			foreach($columns as $key => $column) {
				$this->add_td_class($key, 'minimal');
			}
		}
	}
	
	/* Sets a CSS class to be applied to every cell in a particular column. */
	public function add_td_class($column_number, $class) {
		$this->column_classes[$column_number][] = $class;
	}
	
	/* Create a row of values and optionally set the row's CSS classes */
	public function row($values, $classes = array()) {
		$this->rows[] = $values;
		
		/* In case the user passed a CSS class as a string, recast as an array. */
		if( ! empty($classes)) {
			$classes = (array) $classes;
		}
		/* We use a bitwise operator to alternate the row colours. */
		if($this->row_count & 1) {
			$classes[] = 'odd';
		}
		if( ! empty($classes)) {
			$last_key = count($this->rows) - 1;
			$this->row_classes[$last_key] = $classes;
		}
		
		$this->row_count++;
	}
	
	/* If we have any rows, build and output the table. Otherwise, display $no_rows_message. */
	public function output($no_rows_message = '') {
		if($this->row_count == 0) {
			echo '<p>' . $no_rows_message . '</p>';
			return;
		}
?>
<table>
	<thead>
		<tr>
<?php	
		foreach($this->columns as $key => $column):
?>
			<th<?php if(isset($this->column_classes[$key])) echo ' class="'.implode(' ', $this->column_classes[$key]).'"' ?>><?php echo $column ?></th>
<?php
		endforeach;
?>
		</tr>
	</thead>
	<tbody>
<?php
		foreach($this->rows as $key => $values):
?>
		<tr<?php if(isset($this->row_classes[$key])) echo ' class="'.implode(' ', $this->row_classes[$key]).'"' ?>>
<?php
			foreach($values as $column_key => $value):
?>
			<td<?php if(isset($this->column_classes[$column_key])) echo ' class="'.implode(' ', $this->column_classes[$column_key]).'"' ?>><?php echo $value ?></td>
<?php
			endforeach;
?>
		</tr>
<?php
		endforeach;
?>
	</tbody>
</table>
<?php
	}
}
?>