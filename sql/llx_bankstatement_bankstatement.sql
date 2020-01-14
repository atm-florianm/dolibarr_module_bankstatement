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


CREATE TABLE llx_bankstatement_bankstatement(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	ref varchar(128) DEFAULT '(PROV)' NOT NULL,
	label varchar(128),
    status integer NOT NULL,
    fk_account integer NOT NULL,
    date_start datetime,
    date_end datetime,
	tms timestamp,
	fk_user_import integer,
	date_import datetime,
	fk_user_reconcile integer,
	date_reconcile datetime,
    import_key varchar(14),
    entity integer
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
