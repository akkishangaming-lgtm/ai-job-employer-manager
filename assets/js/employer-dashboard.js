/**
 * AI Job Employer Manager — Frontend JavaScript
 *
 * Handles: sidebar navigation, AJAX CRUD, geolocation,
 * skills tag input, application management, analytics charts,
 * job listing filters, toast notifications, and the apply modal.
 *
 * @package AI_Job_Employer_Manager
 * @version 1.0.0
 */

/* global ajemData, jQuery */
(function ($) {
	'use strict';

	if (typeof ajemData === 'undefined') {
		return;
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Show a toast notification.
	 *
	 * @param {string} message  Text to display.
	 * @param {string} type     'success' | 'error' | '' (default dark).
	 */
	function showToast(message, type) {
		var $toast = $('#ajemToast');
		if (!$toast.length) {
			return;
		}
		$toast.text(message).removeClass('success error').addClass('show');
		if (type) {
			$toast.addClass(type);
		}
		clearTimeout(window._ajemToastTimer);
		window._ajemToastTimer = setTimeout(function () {
			$toast.removeClass('show success error');
		}, 3500);
	}

	/**
	 * REST helper — wraps jQuery.ajax with authentication headers.
	 *
	 * @param {string} method   HTTP method.
	 * @param {string} endpoint Path relative to ajemData.restUrl.
	 * @param {Object} data     Request body.
	 * @returns {jQuery.Deferred}
	 */
	function restRequest(method, endpoint, data) {
		return $.ajax({
			url:         ajemData.restUrl + endpoint,
			method:      method,
			contentType: 'application/json',
			data:        data ? JSON.stringify(data) : null,
			beforeSend:  function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', ajemData.restNonce);
			}
		});
	}

	// ── Sidebar Navigation ────────────────────────────────────────────────────

	/**
	 * Activate a dashboard section without page reload.
	 *
	 * @param {string} sectionKey Section identifier.
	 */
	function showSection(sectionKey) {
		$('.ajem-section').removeClass('active');
		$('#ajem-section-' + sectionKey).addClass('active');
		$('.ajem-nav-item').removeClass('ajem-nav-active');
		$('.ajem-nav-item[data-section="' + sectionKey + '"]').addClass('ajem-nav-active');

		// Lazy-load data for certain sections.
		if (sectionKey === 'applications') {
			loadApplications();
		} else if (sectionKey === 'shortlisted') {
			loadShortlisted();
		} else if (sectionKey === 'analytics') {
			loadAnalytics();
		}
	}

	// Sidebar nav link clicks.
	$(document).on('click', '.ajem-nav-item', function (e) {
		var section = $(this).data('section');
		if (!section) {
			return;
		}
		e.preventDefault();
		showSection(section);
		history.replaceState(null, '', '?section=' + section);

		// Close sidebar on mobile.
		$('#ajemSidebar').removeClass('open');
	});

	// Mobile hamburger.
	$(document).on('click', '#ajemHamburger', function () {
		$('#ajemSidebar').toggleClass('open');
	});

	// Activate correct section on load from URL param.
	(function () {
		var params  = new URLSearchParams(window.location.search);
		var section = params.get('section');
		if (section) {
			showSection(section);
		}
	}());

	// ── Geolocation ───────────────────────────────────────────────────────────

	/**
	 * Detect device location and perform reverse-geocoding via Nominatim.
	 *
	 * @param {Function} callback Called with { lat, lng, country, state, district, city }.
	 */
	function detectLocation(callback) {
		if (!navigator.geolocation) {
			showToast(ajemData.i18n.locationError, 'error');
			return;
		}

		navigator.geolocation.getCurrentPosition(
			function (position) {
				var lat = position.coords.latitude;
				var lng = position.coords.longitude;

				// Reverse geocode via OpenStreetMap Nominatim (free, no API key needed).
				$.getJSON(
					'https://nominatim.openstreetmap.org/reverse',
					{
						lat:            lat,
						lon:            lng,
						format:         'json',
						addressdetails: 1
					},
					function (data) {
						var addr     = data.address || {};
						var result   = {
							lat:      lat,
							lng:      lng,
							country:  addr.country || '',
							state:    addr.state || '',
							district: addr.county || addr.state_district || '',
							city:     addr.city || addr.town || addr.village || addr.municipality || ''
						};
						callback(result);
					}
				).fail(function () {
					// Fall back to coordinates only.
					callback({ lat: lat, lng: lng, country: '', state: '', district: '', city: '' });
				});
			},
			function () {
				showToast(ajemData.i18n.locationError, 'error');
			}
		);
	}

	// Profile — detect location button.
	$(document).on('click', '#ajemDetectLocation', function () {
		var $btn = $(this);
		var $status = $('#ajemLocationStatus');
		$btn.prop('disabled', true);
		$status.text(ajemData.i18n.locationDetect);

		detectLocation(function (loc) {
			$('#ajem_latitude').val(loc.lat);
			$('#ajem_longitude').val(loc.lng);
			$('#ajem_country').val(loc.country);
			$('#ajem_state').val(loc.state);
			$('#ajem_district').val(loc.district);
			$('#ajem_city').val(loc.city);
			$status.text('✅ ' + loc.city + ', ' + loc.state + ', ' + loc.country);
			$btn.prop('disabled', false);
		});
	});

	// Post Job — use company location.
	$(document).on('click', '#ajemDetectJobLocation', function () {
		$('#ajem_job_country').val($('#ajem_country').val());
		$('#ajem_job_state').val($('#ajem_state').val());
		$('#ajem_job_district').val($('#ajem_district').val());
		$('#ajem_job_city').val($('#ajem_city').val());
		$('#ajem_job_latitude').val($('#ajem_latitude').val());
		$('#ajem_job_longitude').val($('#ajem_longitude').val());
		showToast('Location copied from company profile.', 'success');
	});

	// ── Skills Tag Input ──────────────────────────────────────────────────────

	var skills = [];

	function renderSkillTags() {
		var $container = $('#ajemSkillsTags');
		$container.empty();
		skills.forEach(function (skill, index) {
			var $tag = $(
				'<span class="ajem-skill-tag">' +
					$('<span>').text(skill).html() +
					' <span class="ajem-skill-remove" data-index="' + index + '">×</span>' +
				'</span>'
			);
			$container.append($tag);
		});
		$('#ajemRequiredSkills').val(JSON.stringify(skills));
	}

	$(document).on('keydown', '#ajemSkillInput', function (e) {
		if (e.key === 'Enter' || e.key === ',') {
			e.preventDefault();
			var skill = $(this).val().trim().replace(/,+$/, '');
			if (skill && !skills.includes(skill)) {
				skills.push(skill);
				renderSkillTags();
			}
			$(this).val('');
		}
	});

	$(document).on('click', '.ajem-skill-remove', function () {
		var index = parseInt($(this).data('index'), 10);
		skills.splice(index, 1);
		renderSkillTags();
	});

	// ── Profile Form ─────────────────────────────────────────────────────────

	$(document).on('submit', '#ajemProfileForm', function (e) {
		e.preventDefault();
		var $form   = $(this);
		var $status = $('#ajemProfileStatus');
		var $btn    = $form.find('[type=submit]');

		$btn.prop('disabled', true);
		$status.text(ajemData.i18n.saving);

		var data = {};
		$form.serializeArray().forEach(function (field) {
			data[field.name] = field.value;
		});

		restRequest('POST', 'employer/profile', data)
			.done(function (res) {
				if (res.success) {
					$status.text(ajemData.i18n.saved);
					showToast('Profile saved successfully!', 'success');
				} else {
					$status.text(res.message || ajemData.i18n.error);
					showToast(res.message || ajemData.i18n.error, 'error');
				}
			})
			.fail(function (xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : ajemData.i18n.error;
				$status.text(msg);
				showToast(msg, 'error');
			})
			.always(function () {
				$btn.prop('disabled', false);
				setTimeout(function () { $status.text(''); }, 3000);
			});
	});

	// ── Post Job Form ─────────────────────────────────────────────────────────

	$(document).on('submit', '#ajemPostJobForm', function (e) {
		e.preventDefault();
		var $form   = $(this);
		var $status = $('#ajemPostJobStatus');
		var $btn    = $('#ajemPostJobSubmit');

		$btn.prop('disabled', true);
		$status.text(ajemData.i18n.saving);

		var data = {};
		$form.serializeArray().forEach(function (field) {
			data[field.name] = field.value;
		});

		// Skills are stored as JSON in the hidden input.
		data.required_skills = JSON.parse(data.required_skills || '[]');

		restRequest('POST', 'employer/jobs', data)
			.done(function (res) {
				if (res.success) {
					showToast('Job posted successfully!', 'success');
					$status.text('');
					$form[0].reset();
					skills = [];
					renderSkillTags();
					// Switch to My Jobs section.
					setTimeout(function () { showSection('my-jobs'); }, 1000);
				} else {
					$status.text(res.message || ajemData.i18n.error);
					showToast(res.message || ajemData.i18n.error, 'error');
				}
			})
			.fail(function (xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : ajemData.i18n.error;
				$status.text(msg);
				showToast(msg, 'error');
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

	// ── Job Status Change ─────────────────────────────────────────────────────

	$(document).on('change', '.ajem-status-select', function () {
		var $select  = $(this);
		var jobId    = $select.data('job-id');
		var newStatus = $select.val();

		restRequest('PUT', 'employer/jobs/' + jobId + '/status', { status: newStatus })
			.done(function (res) {
				if (res.success) {
					showToast('Status updated.', 'success');
				} else {
					showToast(res.message || ajemData.i18n.error, 'error');
				}
			})
			.fail(function () {
				showToast(ajemData.i18n.error, 'error');
			});
	});

	// ── Delete Job ────────────────────────────────────────────────────────────

	$(document).on('click', '.ajem-delete-job', function () {
		if (!window.confirm(ajemData.i18n.confirmDelete)) {
			return;
		}
		var jobId = $(this).data('job-id');
		var $row  = $(this).closest('tr');

		restRequest('DELETE', 'employer/jobs/' + jobId)
			.done(function (res) {
				if (res.success) {
					$row.fadeOut(300, function () { $row.remove(); });
					showToast('Job deleted.', 'success');
				} else {
					showToast(res.message || ajemData.i18n.error, 'error');
				}
			})
			.fail(function () {
				showToast(ajemData.i18n.error, 'error');
			});
	});

	// ── Applications ──────────────────────────────────────────────────────────

	/**
	 * Load all employer applications and render them in the Applications section.
	 */
	function loadApplications() {
		var $container = $('#ajemApplicationsList');
		if (!$container.length) {
			return;
		}

		restRequest('GET', 'employer/applications?per_page=50')
			.done(function (res) {
				var apps = res.applications || [];
				if (!apps.length) {
					$container.html('<p>' + (ajemData.i18n.noApplications || 'No applications yet.') + '</p>');
					return;
				}

				var statusOptions = ['applied', 'viewed', 'shortlisted', 'rejected', 'interview_scheduled', 'hired'];
				var rows = apps.map(function (app) {
					var opts = statusOptions.map(function (s) {
						var sel = s === app.status ? ' selected' : '';
						return '<option value="' + s + '"' + sel + '>' + s.replace('_', ' ') + '</option>';
					}).join('');

					return '<tr>' +
						'<td>' + escapeHtml(app.job_title || 'N/A') + '</td>' +
						'<td>' + escapeHtml(app.full_name || '#' + app.candidate_id) + '</td>' +
						'<td>' +
							'<select class="ajem-status-select ajem-app-status-select ajem-status-select" data-app-id="' + app.id + '">' + opts + '</select>' +
						'</td>' +
						'<td>' + escapeHtml(app.applied_at ? app.applied_at.substr(0, 10) : '') + '</td>' +
						'<td>' +
							'<button class="ajem-btn-icon ajem-add-note" data-app-id="' + app.id + '" title="Add note">📝</button>' +
						'</td>' +
					'</tr>';
				});

				$container.html(
					'<div class="ajem-table-wrap">' +
					'<table class="ajem-table"><thead><tr>' +
						'<th>Job</th><th>Candidate</th><th>Status</th><th>Applied</th><th>Notes</th>' +
					'</tr></thead><tbody>' + rows.join('') + '</tbody></table></div>'
				);
			})
			.fail(function () {
				$container.html('<p>' + ajemData.i18n.error + '</p>');
			});
	}

	// Application status change.
	$(document).on('change', '.ajem-app-status-select', function () {
		var appId  = $(this).data('app-id');
		var status = $(this).val();

		restRequest('PUT', 'employer/applications/' + appId + '/status', { status: status })
			.done(function (res) {
				if (res.success) {
					showToast('Status updated.', 'success');
				}
			});
	});

	// Add note button.
	$(document).on('click', '.ajem-add-note', function () {
		var appId = $(this).data('app-id');
		var note  = window.prompt('Enter note for this application:');
		if (note === null) {
			return;
		}

		restRequest('POST', 'employer/applications/' + appId + '/notes', { notes: note })
			.done(function (res) {
				if (res.success) {
					showToast('Note saved.', 'success');
				}
			});
	});

	// ── Shortlisted Candidates ────────────────────────────────────────────────

	function loadShortlisted() {
		var $container = $('#ajemShortlistedList');
		if (!$container.length) {
			return;
		}

		restRequest('GET', 'employer/shortlist?per_page=50')
			.done(function (res) {
				var list = res.shortlisted || [];
				if (!list.length) {
					$container.html('<p>No shortlisted candidates yet.</p>');
					return;
				}

				var rows = list.map(function (item) {
					return '<tr>' +
						'<td>#' + item.candidate_id + '</td>' +
						'<td>' + (item.job_id ? '#' + item.job_id : '—') + '</td>' +
						'<td>' + escapeHtml(item.notes || '') + '</td>' +
						'<td>' + escapeHtml(item.shortlisted_at ? item.shortlisted_at.substr(0, 10) : '') + '</td>' +
					'</tr>';
				});

				$container.html(
					'<div class="ajem-table-wrap"><table class="ajem-table"><thead><tr>' +
						'<th>Candidate</th><th>Job</th><th>Notes</th><th>Date</th>' +
					'</tr></thead><tbody>' + rows.join('') + '</tbody></table></div>'
				);
			});
	}

	// ── Analytics ─────────────────────────────────────────────────────────────

	function loadAnalytics() {
		restRequest('GET', 'employer/analytics')
			.done(function (res) {
				renderAnalyticsChart(res.stats || {});
				renderTopJobs(res.top_jobs || []);
			});
	}

	function renderAnalyticsChart(stats) {
		var $chart = $('#ajemAnalyticsChart');
		if (!$chart.length) {
			return;
		}

		var items = [
			{ label: 'Total Applications', value: stats.total_applications || 0 },
			{ label: 'This Week',           value: stats.applications_this_week || 0 },
			{ label: 'This Month',          value: stats.applications_this_month || 0 },
			{ label: 'Shortlisted',         value: stats.shortlisted || 0 },
			{ label: 'Hired',               value: stats.hired || 0 }
		];

		var max = Math.max.apply(null, items.map(function (i) { return i.value; })) || 1;

		var bars = items.map(function (item) {
			var pct = Math.round((item.value / max) * 100);
			return '<div style="margin-bottom:14px;">' +
				'<div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">' +
					'<span>' + escapeHtml(item.label) + '</span>' +
					'<strong>' + item.value + '</strong>' +
				'</div>' +
				'<div style="background:#e5e7eb;border-radius:4px;height:12px;">' +
					'<div style="background:var(--ajem-primary);height:12px;border-radius:4px;width:' + pct + '%;transition:width 0.5s;"></div>' +
				'</div>' +
			'</div>';
		});

		$chart.html(bars.join(''));
	}

	function renderTopJobs(jobs) {
		var $container = $('#ajemTopJobs');
		if (!$container.length) {
			return;
		}

		if (!jobs.length) {
			$container.html('<p>No job data yet.</p>');
			return;
		}

		var rows = jobs.map(function (job) {
			return '<tr>' +
				'<td><a href="' + ajemData.siteUrl + '/jobs/' + job.job_slug + '" target="_blank">' + escapeHtml(job.job_title) + '</a></td>' +
				'<td>' + job.views_count + '</td>' +
				'<td>' + job.applications_count + '</td>' +
				'</tr>';
		});

		$container.html(
			'<table class="ajem-table"><thead><tr><th>Job</th><th>Views</th><th>Apps</th></tr></thead>' +
			'<tbody>' + rows.join('') + '</tbody></table>'
		);
	}

	// ── Apply Modal ───────────────────────────────────────────────────────────

	// Open modal.
	$(document).on('click', '#ajemApplyBtn, #ajemApplyBtnSidebar', function () {
		$('#ajemApplicationModal').show();
		// Load candidate resumes if candidate plugin is active.
		loadCandidateResumes();
	});

	// Close modal.
	$(document).on('click', '#ajemModalClose, #ajemModalOverlay', function () {
		$('#ajemApplicationModal').hide();
	});

	// Close on Escape key.
	$(document).on('keydown', function (e) {
		if (e.key === 'Escape') {
			$('#ajemApplicationModal').hide();
		}
	});

	/**
	 * Attempt to load candidate resumes from the candidate plugin REST API.
	 * Silently fails if the plugin is not installed.
	 */
	function loadCandidateResumes() {
		$.ajax({
			url:         ajemData.restUrl.replace('ajem/v1/', '') + 'ajcm/v1/resumes',
			method:      'GET',
			beforeSend:  function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', ajemData.restNonce);
			}
		}).done(function (res) {
			var resumes = res.resumes || [];
			var $select = $('#ajemResumeSelect');
			$select.find('option:not(:first)').remove();
			resumes.forEach(function (r) {
				$select.append('<option value="' + escapeHtml(String(parseInt(r.id, 10))) + '">' + escapeHtml(r.resume_title || 'Resume #' + r.id) + '</option>');
			});
		});
	}

	// Submit application form.
	$(document).on('submit', '#ajemApplicationForm', function (e) {
		e.preventDefault();
		var $form   = $(this);
		var $status = $('#ajemApplicationStatus');
		var $btn    = $('#ajemSubmitApplication');

		var jobId = $form.find('[name=job_id]').val();
		if (!jobId) {
			return;
		}

		$btn.prop('disabled', true);
		$status.text(ajemData.i18n.saving);

		var data = {
			cover_letter: $form.find('[name=cover_letter]').val(),
			resume_id:    $form.find('[name=resume_id]').val() || null
		};

		restRequest('POST', 'jobs/' + jobId + '/apply', data)
			.done(function (res) {
				if (res.success) {
					$('#ajemApplicationModal').hide();
					showToast('Application submitted successfully!', 'success');
					// Disable apply buttons.
					$('#ajemApplyBtn, #ajemApplyBtnSidebar').prop('disabled', true).text('✅ Applied');
				} else {
					$status.text(res.message || ajemData.i18n.error);
					showToast(res.message || ajemData.i18n.error, 'error');
				}
			})
			.fail(function (xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : ajemData.i18n.error;
				$status.text(msg);
				showToast(msg, 'error');
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

	// ── Copy Link (Share) ─────────────────────────────────────────────────────

	$(document).on('click', '#ajemCopyLink', function () {
		if (navigator.clipboard) {
			navigator.clipboard.writeText(window.location.href).then(function () {
				showToast('Link copied to clipboard!', 'success');
			});
		} else {
			var $tmp = $('<input>').val(window.location.href).appendTo('body').select();
			document.execCommand('copy');
			$tmp.remove();
			showToast('Link copied!', 'success');
		}
	});

	// ── Location Autocomplete (REST) ──────────────────────────────────────────

	$('[data-location-autocomplete]').on('input', function () {
		var $input = $(this);
		var query  = $input.val();
		if (query.length < 2) {
			return;
		}

		$.get(ajemData.restUrl + 'locations/search?q=' + encodeURIComponent(query))
			.done(function (results) {
				// TODO: Render autocomplete dropdown — implementation depends on UI library.
				console.log('Location results:', results);
			});
	});

	// ── Track Job View via REST ───────────────────────────────────────────────

	if (typeof ajemData.currentJobId !== 'undefined' && ajemData.currentJobId) {
		restRequest('GET', 'jobs/' + ajemData.currentJobId + '/view');
	}

	// ── Utility ───────────────────────────────────────────────────────────────

	/**
	 * Escape HTML special characters to prevent XSS.
	 *
	 * @param {string} str Raw string.
	 * @returns {string} Escaped string.
	 */
	function escapeHtml(str) {
		if (typeof str !== 'string') {
			return String(str);
		}
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return str.replace(/[&<>"']/g, function (m) { return map[m]; });
	}

}(jQuery));
