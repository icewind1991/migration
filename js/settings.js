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
	var canBeValidCloudId = function (cloudId) {
		return cloudId.indexOf('@') !== -1;
	};

	var checkCloudId = function (cloudId) {
		return $.get('/apps/migration/check', {
			'remoteCloudId': cloudId
		});
	};

	var cloudIdInput = $('#migration_cloudid');
	var cloudIdRow = cloudIdInput.parent().parent();
	cloudIdInput.bind("change blur keyup mouseup", _.debounce(function (event) {
		var cloudId = cloudIdInput.val();
		if (cloudId.length > 0) {
			cloudIdRow.addClass('pending');
		}else {
			cloudIdRow.removeClass('pending');
			cloudIdRow.removeClass('error');
			cloudIdRow.removeClass('ok');
		}

	}, 100));
});