<div class="report curvybox white">
	<div class="reportInputs">
		<h3 class="georgia">Merged patients</h3>
		<div>
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
							<li class="<?php if ($i%2 == 0) {?>even<?php }else{?>odd<?php }?>" data-attr-id="<?php echo $merged->id?>">
								<span class="column_id"><input class="mergedPatient" type="checkbox" value="<?php echo $merged->id?>" /></span>
								<span class="column_oe"><?php echo $merged->patient->hos_num?> <?php echo $merged->patient->first_name?> <?php echo $merged->patient->last_name?></span>
								<span class="column_pas"><?php echo $merged->new_hos_num?> <?php echo $merged->new_first_name?> <?php echo $merged->new_last_name?></span>
							</li>
						<?php }?>
					</div>
				</ul>
			</form>
		</div>
	</div>
</div>
<script type="text/javascript">
	$('input.checkall').click(function() {
		$('input.mergedPatient').attr('checked',$(this).is(':checked') ? 'checked' : false);
	});
</script>
