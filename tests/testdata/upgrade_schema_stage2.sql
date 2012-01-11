-- dbsteward schema stage 2 upgrade file generated Tue, 10 Jan 2012 22:25:56 -0500
-- Old definition:  /usr/home/nfs/nkiraly/public_html/releng/dbsteward/trunk/tests/testdata/type_diff_xml_a_composite.xml
-- New definition:  /usr/home/nfs/nkiraly/public_html/releng/dbsteward/trunk/tests/testdata/type_diff_xml_b_composite.xml
BEGIN; -- STRIP_SLONY: SlonyI installs/upgrades strip this line, the rest need to keep the install transactional --

CREATE OR REPLACE VIEW log."user_event_log"
	AS 
        SELECT
          user_info.user.user_id, user_name, user_role
          event_log_id, event_type, event_date, event_detail
        FROM log.event
        JOIN user_info.user ON (user_info.user.user_id = log.event.user_id)
      ;
ALTER VIEW log."user_event_log"
	OWNER TO deployment;
GRANT SELECT ON  log.user_event_log TO dbsteward_phpunit_app;

-- SQL STAGE SCHEMA2 COMMANDS


COMMIT; -- STRIP_SLONY: SlonyI installs/upgrades strip this line, the rest need to keep the install transactional --
