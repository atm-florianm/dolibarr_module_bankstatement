-- Copyright (C)  2020 ATM Consulting <support@atm-consulting.fr>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see https://www.gnu.org/licenses/.

CREATE TABLE llx_bankstatement_importformat(
    rowid             integer AUTO_INCREMENT PRIMARY KEY,
    fk_account        integer NULL,
    columnmapping     varchar(255),
    delimiter         varchar(4),
    dateformat        varchar(32),
    lineending        varchar(24), -- allow for longer regexp
    escapechar        varchar(4),
    enclosure         varchar(4),
    skipfirstline     tinyint(4),
    rotatecsv         tinyint(4),
    usedirection      tinyint(4),
    directioncredit   varchar(64),
    directiondebit    varchar(64),
    tms               timestamp,
    import_key        varchar(14)
) ENGINE=innodb;

