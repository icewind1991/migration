/**
 * @copyright Copyright (c) 2017, Robin Appelman <robin@icewind.nl>
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

$(document).ready(function () {
	var working = false;

	var canBeValidCloudId = function (cloudId) {
		return cloudId.indexOf('@') !== -1;
	};

	var checkCloudId = function (cloudId) {
		return $.get(OC.generateUrl('apps/migration/check'), {
			'remoteCloudId': cloudId
		});
	};

	var checkCredentials = function (cloudId, password) {
		return $.post(OC.generateUrl('apps/migration/check_credentials'), {
			'remoteCloudId': cloudId,
			'remotePassword': password
		});
	};

	var migrateButton = $('#migration_migrate');

	var cloudIdInput = $('#migration_cloudid');
	var cloudIdRow = cloudIdInput.parent().parent();
	var cloudIdStatus = cloudIdRow.find('.status');
	var oldCloudId = '';

	var setStatus = function (row, span, status, message) {
		row.removeClass('indeterminate');
		row.removeClass('pending');
		row.removeClass('error');
		row.removeClass('ok');
		row.addClass(status);
		if (message) {
			span.tooltip({
				placement: 'right',
				title: message
			});
		} else {
			span.tooltip('destroy');
		}

		if (!working && (cloudIdRow.hasClass('ok') || cloudIdRow.hasClass('indeterminate')) && passwordRow.hasClass('ok')) {
			migrateButton.attr('disabled', null);
		} else {
			migrateButton.attr('disabled', 'disabled');
		}
	};

	var setCloudIdStatus = setStatus.bind(null, cloudIdRow, cloudIdStatus);

	cloudIdInput.bind("change blur keyup mouseup", _.debounce(function () {
		var cloudId = cloudIdInput.val();
		if (oldCloudId === cloudId) {
			return;
		}
		oldCloudId = cloudId;
		if (cloudId.length > 0) {
			if (canBeValidCloudId(cloudId)) {
				setCloudIdStatus('pending', '');
				doPasswordCheck(cloudId, passwordInput.val());
				checkCloudId(cloudId).then(function (result) {
					if (result && result.installed) {
						var majorVersion = parseInt(result.version, 10);
						if (result.protocol === 'http') {
							setCloudIdStatus('indeterminate', t('migration', 'Insecure connection'));
						} else if (majorVersion >= 12) {
							setCloudIdStatus('ok', '');
						} else {
							setCloudIdStatus('error', t('migration', 'Nextcloud 11 and lower are not supported for migration'));
						}
					} else {
						setCloudIdStatus('error', t('migration', 'No Nextcloud instance found at remote address'));
					}
				});
			} else {
				cloudIdRow.addClass('error');
			}
		} else {
			setCloudIdStatus('', '');
		}

	}.bind(this), 200));

	var passwordInput = $('#migration_password');
	var passwordRow = passwordInput.parent().parent();
	var passwordStatus = passwordRow.find('.status');
	var oldPassword = '';

	var setPasswordStatus = setStatus.bind(null, passwordRow, passwordStatus);

	var doPasswordCheck = function (cloudId, password) {
		if (password.length > 0) {
			setPasswordStatus('pending', '');
			checkCredentials(cloudId, password).then(function (result) {
				if (result) {
					setPasswordStatus('ok', '');
				} else {
					setPasswordStatus('error', 'Invalid credentials');
				}
			}, function () {
				setPasswordStatus('error', 'Invalid credentials');
			});
		} else {
			setPasswordStatus('', '');
		}
	};

	passwordInput.bind("change blur keyup mouseup", _.debounce(function () {
		var password = passwordInput.val();
		if (oldPassword === password) {
			return;
		}
		oldPassword = password;
		doPasswordCheck(cloudIdInput.val(), password);

	}.bind(this), 200));

	migrateButton.bind('click', function () {
		if (working) {
			return;
		}
		working = true;
		migrateButton.attr('disabled', 'disabled');
		migrateButton.val(t('migration', 'Working...'));
		var eventSource = new OC.EventSource(OC.generateUrl('apps/migration/migrate'), {
			'remoteCloudId': cloudIdInput.val(),
			'remotePassword': passwordInput.val()
		});
		eventSource.listen('progress', function (progress) {
			if (progress.step === 'files') {

			}
			console.log(progress);
		});
		eventSource.listen('error', function(error) {
			console.log(error);
			working = false;
			migrateButton.val(t('migration', 'Migrate'));
		});
		eventSource.listen('done', function() {
			working = false;
			migrateButton.val(t('migration', 'Done'));
		});
	})
});
