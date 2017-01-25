<?php
script('migration', 'settings');
style('migration', 'settings');
?>
<form autocomplete="false" class="section" action="#"
	  id="migration">
	<h2><?php p($l->t('Migration')); ?></h2>

	<p><?php p($l->t('You can import your data from a remote Nextcloud instance to migrate to a new instance')); ?></p>

	<table>
		<tr>
			<td>
				<label for="migration_cloudid"><?php p($l->t('Remote cloud id')); ?></label>
			</td>
			<td>
				<input id="migration_cloudid" name="cloudid"
					   placeholder="<?php p($l->t('me@example.com')); ?>"/>
			</td>
			<td>
				<span class="status"/>
			</td>
		</tr>
		<tr>
			<td>
				<label for="migration_password"><?php p($l->t('Remote password')); ?></label>
			</td>
			<td>
				<input id="migration_password" autocomplete="new-password"
					   name="password" type="password"/>
			</td>
			<td>
				<span class="status spinner"/>
			</td>
		</tr>
		<tr>
			<td>
				<input type="button" disabled id="migration_migrate" value="<?php p($l->t('Migrate')); ?>"/>
			</td>
		</tr>
	</table>
</form>