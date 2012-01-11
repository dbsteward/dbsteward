-- dbsteward schema stage 1 upgrade file generated Tue, 10 Jan 2012 22:25:56 -0500
-- Old definition:  /usr/home/nfs/nkiraly/public_html/releng/dbsteward/trunk/tests/testdata/type_diff_xml_a_composite.xml
-- New definition:  /usr/home/nfs/nkiraly/public_html/releng/dbsteward/trunk/tests/testdata/type_diff_xml_b_composite.xml
BEGIN; -- STRIP_SLONY: SlonyI installs/upgrades strip this line, the rest need to keep the install transactional --


-- SQL STAGE SCHEMA0 COMMANDS

DROP VIEW log."user_event_log";

-- type event_type definition migration (1/4): dependant tables column type alteration
ALTER TABLE log."event" ALTER COLUMN "event_type" TYPE text;

-- type event_type definition migration (2/4): drop old type
DROP TYPE otherschema."event_type";

-- type event_type definition migration (3/4): recreate type with new definition
CREATE TYPE otherschema."event_type" AS ENUM ('Read','Write','Delete','Update','Transmit');

-- type event_type definition migration (4/4): dependant tables type restoration
ALTER TABLE log."event" ALTER COLUMN "event_type" TYPE otherschema."event_type" USING "event_type"::otherschema."event_type";


-- SQL STAGE SCHEMA1 COMMANDS


COMMIT; -- STRIP_SLONY: SlonyI installs/upgrades strip this line, the rest need to keep the install transactional --
