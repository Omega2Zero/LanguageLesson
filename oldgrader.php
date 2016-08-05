	<table id="grader_content" style="table-layout: fixed;">
		<tr>
			<td id="stunames_column_cell" class="grader_content_column_cell">

			<?php // print out the frozen left-hand column of student names as its own table ?>
				<div id="stunames_column_container">
					<table id="stunames_column">
						<?php for ($i=0; $i<$numrows; $i++) { echo '<tr class="offset_row"></tr>'; } ?>
						<tr class="header_row">
							<td id="student_column_header_cell" class="grader header_cell"><span class="rotate-text">
								<?php echo get_string('graderstudentcolumnname','languagelesson'); ?></span></td>
						</tr>
						<?php echo $namesContents; ?>
					</table>
				</div>
		
			</td>
			<td id="languagelesson_map_cell" class="grader_content_column_cell">

				<div id="languagelesson_map_container">
					<table id="languagelesson_map_table" class="grader">
				<?php
				// use style="overflow:auto" to get the scrollbar
				echo $btheaders;
				echo $colheaders;
				echo $gridContents;
				?>
					</table>
				</div>

			</td>
			<td id="right_column_cell" class="grader_content_column_cell">

				<div id="dynamic_content_container">
					<table id="dynamic_content">
						<?php for ($i=0; $i<$numrows; $i++) { echo '<tr class="offset_row"></tr>'; } ?>
						<tr class="header_row">
							<td class=\"grader\" id=\"assign_grade_column_header_cell\">
								<?php echo get_string("assigngradecolumnheader", 'languagelesson', $DB->get_field('languagelesson', 'grade',
											array('id'=>$lesson->id))); ?></td>
							<td class=\"grader\" id=\"saved_grade_column_header_cell\">
								<?php echo get_string("savedgradecolumnheader", 'languagelesson'); ?></td>
						</tr>
						<?php
						echo $rightContents;
						print_submission_buttons_row();
						?>
					</table>
				</div>
			
			</td>
		</tr>

	</table>