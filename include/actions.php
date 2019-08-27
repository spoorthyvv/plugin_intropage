<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2015-2019 Petr Macek                                      |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | https://github.com/xmacan/                                              |
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

if (isset_request_var('intropage_action') &&
	get_filter_request_var('intropage_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-z0-9_-]+)$/')))) {
	$values = explode('_', get_request_var('intropage_action'));
	// few parameters from input type select has format reset_all, refresh_180, ... first is action
	$action = array_shift($values);
	$value  = implode('_', $values);

	switch ($action) {

	// close panel
	case 'droppanel':
		if (get_filter_request_var('panel_id')) {
			db_execute_prepared('DELETE FROM plugin_intropage_user_setting
				WHERE user_id = ? AND id = ?',
				array($_SESSION['sess_user_id'], get_request_var('panel_id')));
		}

		// user can close debug panels like others
		// if (get_request_var('panel_id') == 999) {
		//	set_user_setting('intropage_debug', 0);
		// }
		break;


	// favourite graphs
	case 'favgraph':
		if (get_filter_request_var('graph_id')) {
			// already fav?
			if (db_fetch_cell('SELECT COUNT(*) FROM plugin_intropage_user_setting WHERE user_id=' . $_SESSION['sess_user_id'] .
					' AND fav_graph_id=' . get_request_var('graph_id')) > 0) {
				db_execute('DELETE FROM plugin_intropage_user_setting WHERE user_id=' . $_SESSION['sess_user_id'] . ' and fav_graph_id=' .  get_request_var('graph_id'));
			} else { // add to fav
				// priority for new panel:
				$prio = db_fetch_cell("SELECT priority
					FROM plugin_intropage_panel
					WHERE panel='intropage_favourite_graph'");

				db_execute_prepared('REPLACE INTO plugin_intropage_user_setting
					(user_id, priority, panel, fav_graph_id)
					VALUES (?, ?, ?, ?)',
					array(
						$_SESSION['sess_user_id'],
						$prio,
						'intropage_favourite_graph',
						get_request_var('graph_id')
					)
				);
			}
		}
		break;

	// panel order
	case 'order':
		if (isset_request_var('xdata')) {
			$error = false;
			$order = array();
			foreach (get_request_var('xdata') as $data) {
				list($a, $b) = explode('_', $data);
				if (filter_var($b, FILTER_VALIDATE_INT)) {
					array_push($order, $b);
				} else {
					$error = true;
				}

				if (!$error) {
					$_SESSION['intropage_order']         = $order;
					$_SESSION['intropage_changed_order'] = true;
				}
			}
		}
		break;

	// reset all panels
	case 'reset':
		if ($value == 'all') {
			unset($_SESSION['intropage_changed_order'], $_SESSION['intropage_order']);
			db_execute_prepared('DELETE FROM plugin_intropage_user_setting
				WHERE user_id = ?',
				array($_SESSION['sess_user_id']));

			// default values
			set_user_setting('intropage_display_important_first', read_config_option('intropage_display_important_first'));
			set_user_setting('intropage_autorefresh', read_config_option('intropage_autorefresh'));
		} elseif ($value == 'order') {
			unset($_SESSION['intropage_changed_order'], $_SESSION['intropage_order']);
		}
		break;

	case 'addpanel':
		if (preg_match('/^[a-z0-9\-\_]+$/i', $value)) {
			db_execute('REPLACE INTO plugin_intropage_user_setting
				(user_id, panel, priority)
				SELECT ' . $_SESSION['sess_user_id'] . ', panel, priority
				FROM plugin_intropage_panel
				WHERE panel="' . $value . '" LIMIT 1');
		}
		break;

	case 'refresh':
		if ($value == 0 || $value == 60 || $value == 180 || $value == 600) {
			set_user_setting('intropage_autorefresh', $value);
		}
		break;

	case 'debug':
		if ($value == 'ena') {
			set_user_setting('intropage_debug', 1);
		}
		if ($value == 'disa') {
			set_user_setting('intropage_debug', 0);
		}
		break;

	case 'important':
		if ($value == 'first') {
			set_user_setting('intropage_display_important_first', 'on');
			unset($_SESSION['intropage_changed_order'], $_SESSION['intropage_order']);
		} else {
			set_user_setting('intropage_display_important_first', 'off');
			unset($_SESSION['intropage_changed_order'], $_SESSION['intropage_order']);
		}
		break;

	case 'loginopt':
		if ($value == 'intropage') { // SELECT login_opts FROM user_auth WHERE id
			db_fetch_cell_prepared('UPDATE user_auth SET login_opts = 4 WHERE id = ?', array($_SESSION['sess_user_id']));
		} elseif ($value == 'graph') {
			db_fetch_cell_prepared('UPDATE user_auth SET login_opts = 3 WHERE id = ?', array($_SESSION['sess_user_id']));
		}
	}
}

