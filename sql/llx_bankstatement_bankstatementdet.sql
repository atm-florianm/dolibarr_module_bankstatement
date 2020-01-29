-- Copyright (C) 2020  ATM Consulting <support@atm-consulting.fr>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.


CREATE TABLE llx_bankstatement_bankstatementdet(
	-- BEGIN MODULEBUILDER FIELDS
	rowid             integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	date              datetime NOT NULL,     -- date of bank transaction
	label             varchar(128),          -- label
	amount            double(24,8) NOT NULL, -- amount of transaction (either >0 or <0);
	                                         -- 0 should not occur (will be handled in later versions)
	                                         -- (in previous drafts we had a 'direction' column for the sign)
	status            tinyint NOT NULL,      -- 0: UNRECONCILED;  1: RECONCILED
	fk_bankstatement  integer NOT NULL,      -- rowid of llx_bankstatement_bankstatement
	fk_user_reconcile integer                -- rowid of llx_user
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
