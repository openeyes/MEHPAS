<div class="report curvybox white">
	<div class="reportInputs">
		<h3 class="georgia">Merged patients</h3>
		<div>
			<?php echo EventAction::button('Merge selected', 'merge1', array('colour' => 'blue'), array('class' => 'mergeSelected'))->toHtml()?>
			<img class="loader" style="display: none;" src="<?php echo Yii::app()->createUrl('img/ajax-loader.gif')?>" alt="loading..." />
			<form id="admin_merged_patients">
				<ul class="grid reduceheight">
					<li class="header">
						<span class="column_id"><input type="checkbox" class="checkall" /></span>
						<span class="column_oe">OE</span>
						<span class="column_pas">PAS</span>
					</li>
					<div class="sortable">
						<?php
						foreach ($patients as $i => $merged) {?>
							<li class="<?php if ($i%2 == 0) {?>even<?php } else {?>odd<?php }?>" data-attr-id="<?php echo $merged->id?>">
								<span class="column_id"><input class="mergedPatient" type="checkbox" value="<?php echo $merged->id?>" /></span>
								<span class="column_oe"><?php echo $merged->patient->hos_num?> <?php echo $merged->patient->first_name?> <?php echo $merged->patient->last_name?></span>
								<span class="column_pas"><?php echo $merged->new_hos_num?> <?php echo $merged->new_first_name?> <?php echo $merged->new_last_name?></span>
							</li>
						<?php }?>
					</div>
				</ul>
			</form>
			<?php echo EventAction::button('Merge selected', 'merge2', array('colour' => 'blue'), array('class' => 'mergeSelected'))->toHtml()?>
			<img class="loader" style="display: none;" src="<?php echo Yii::app()->createUrl('img/ajax-loader.gif')?>" alt="loading..." />
		</div>
	</div>
</div>
<script type="text/javascript">
	$('input.checkall').click(function() {
		$('input.mergedPatient').attr('checked',$(this).is(':checked') ? 'checked' : false);
	});
	handleButton($('button.mergeSelected'),function() {
		var ids = [];
		$('input.mergedPatient:checked').map(function() {
			ids.push($(this).val());
		});

		if (ids.length == 0) {
			enableButtons();
			alert('Please select one or more patients to merge.');
			return false;
		}

		$.ajax({
			'type': 'POST',
			'url': baseUrl+'/mehpas/admin/doMerge',
			'data': $.param({id: ids}),
			'success': function(html) {
				if (html == "1") {
					window.location.reload();
				} else {
					alert('Something went wrong trying to merge the selected patients, please try again or contact support for assistance.');
				}
			}
		});

		return false;
	});
</script>
