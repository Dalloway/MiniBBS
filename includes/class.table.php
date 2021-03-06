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
	
	/* A CSS class to be applied to <table>. */
	private $table_class;
	
	/**
	 * Sets the content of each <th> cell, and optionally the primary column.
	 * @param  array  $columns  An array of values for the header cells.
	 * @param  mixed  $primary  An int or array of ints numbering columns *not* to be set to class="minimal".
	 * @param  string $class    A CSS class to be assigned to the table.
	 */
	public function __construct($columns = array(), $primary = null, $class = null) {
		$this->columns = (array) $columns;
		
		if(isset($class)) {
			$this->table_class = $class;
		}
		
		/* Remove primary columns from the array. */
		if(isset($primary)) {
			$primary = (array) $primary;

			foreach($primary as $key) {
				unset($columns[$key]);
			}
		}
		
		/* All secondary columns will be shrunk to make room for the primary columns. */
		foreach($columns as $key => $column) {
			$this->add_td_class($key, 'minimal');
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
	
	/* Returns the table when object is cast as a string. */
	public function __toString() {
		return $this->output('', true);
	}
	
	/* If we have any rows, build and output the table. Otherwise, display $no_rows_message. */
	public function output($no_rows_message = '', $return = false) {
		if($return) {
			/* The table will be returned instead of output directly*/
			ob_start();
		}
	
		if($this->row_count == 0):
			echo '<p>' . $no_rows_message . '</p>';
		else:
?>
<table<?php if(isset($this->table_class)) echo ' class="'.$this->table_class.'"'?>>
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
		endif;
		
		if($return) {
			$table = ob_get_contents();
			ob_end_clean();
			
			return $table;
		}
	}
}
?>