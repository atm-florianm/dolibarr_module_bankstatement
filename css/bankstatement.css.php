<?php
/* Copyright (C) 2020 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    bankstatement/css/bankstatement.css.php
 * \ingroup bankstatement
 * \brief   CSS file for module BankStatement.
 */

// Define css type
header('Content-type: text/css');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
if (empty($dolibarr_nocache)) header('Cache-Control: max-age=10800, public, must-revalidate');
else header('Cache-Control: no-cache');

?>

#setupConfLabelColumn { width: 55%; } /* Label <col> of setup page table */
#setupConfValueColumn {	} /* Value <col> of setup page table */


.credit_line > td.linecolcredit {
	color: green;
}
.debit_line > td.linecoldebit {
	color: red;
}

.hide_conf {
	display: none;
}
