-- full database definition file generated Tue, 10 Jan 2012 22:25:55 -0500
BEGIN; -- STRIP_SLONY: SlonyI installs/upgrades strip this line, the rest need to keep the install transactional

CREATE SCHEMA dbsteward;
ALTER SCHEMA dbsteward OWNER TO deployment;
CREATE SCHEMA otherschema;
ALTER SCHEMA otherschema OWNER TO deployment;
CREATE SCHEMA user_info;
ALTER SCHEMA user_info OWNER TO deployment;
CREATE SCHEMA log;
ALTER SCHEMA log OWNER TO deployment;
CREATE TYPE otherschema."event_type" AS ENUM ('Read','Write','Delete');
CREATE OR REPLACE FUNCTION dbsteward.db_config_parameter(config_parameter text, config_value text) RETURNS text
  AS $_$

        DECLARE
          q text;
          name text;
          n text;
        BEGIN
          SELECT INTO name current_database();
          q := 'ALTER DATABASE ' || name || ' SET ' || config_parameter || ' ''' || config_value || ''';';
          n := 'DB CONFIG CHANGE: ' || q;
          RAISE NOTICE '%', n;
          EXECUTE q;
          RETURN n;
        END;
      
	$_$
LANGUAGE plpgsql VOLATILE; -- DBSTEWARD_FUNCTION_DEFINITION_END
ALTER FUNCTION dbsteward.db_config_parameter(config_parameter text, config_value text) OWNER TO deployment;
COMMENT ON FUNCTION dbsteward.db_config_parameter(config_parameter text, config_value text) IS 'used to push configurationParameter values permanently into the database configuration';

CREATE TABLE otherschema."othertable" (
	"othertable_id" int,
	"othertable_name" varchar(100) NOT NULL,
	"othertable_detail" text NOT NULL
);
COMMENT ON TABLE otherschema."othertable" IS 'othertable for other data';

ALTER TABLE otherschema."othertable" OWNER TO deployment;

GRANT SELECT ON TABLE otherschema.othertable TO dbsteward_phpunit_app;

CREATE TABLE user_info."user" (
	"user_id" bigserial,
	"user_name" varchar(100) NOT NULL,
	"user_role" varchar(100) NOT NULL,
	"user_create_date" timestamp with time zone DEFAULT NOW() NOT NULL
);
COMMENT ON TABLE user_info."user" IS 'user event log';

ALTER TABLE user_info."user" OWNER TO deployment;

ALTER TABLE user_info.user_user_id_seq OWNER TO deployment;

GRANT SELECT, INSERT, UPDATE ON TABLE user_info.user TO dbsteward_phpunit_app;
GRANT SELECT,UPDATE ON SEQUENCE user_info.user_user_id_seq TO dbsteward_phpunit_app;

CREATE TABLE log."event" (
	"event_id" bigserial,
	"user_id" bigint NOT NULL,
	"event_type" otherschema.event_type NOT NULL,
	"event_date" date DEFAULT NOW() NOT NULL,
	"event_detail" text NOT NULL
);
COMMENT ON TABLE log."event" IS 'user event log';

ALTER TABLE log."event" OWNER TO deployment;

ALTER TABLE log.event_event_id_seq OWNER TO deployment;

GRANT SELECT, INSERT ON TABLE log.event TO dbsteward_phpunit_app;
GRANT SELECT,UPDATE ON SEQUENCE log.event_event_id_seq TO dbsteward_phpunit_app;


ALTER TABLE otherschema."othertable"
	ADD CONSTRAINT "othertable_pkey" PRIMARY KEY ("othertable_id");
ALTER TABLE user_info."user"
	ADD CONSTRAINT "user_pkey" PRIMARY KEY ("user_id");
ALTER TABLE log."event"
	ADD CONSTRAINT "event_pkey" PRIMARY KEY ("event_id");

ALTER TABLE log."event"
	ADD CONSTRAINT "event_user_id_fkey" FOREIGN KEY (user_id) REFERENCES user_info.user("user_id");


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

SELECT dbsteward.db_config_parameter('TIME ZONE', 'America/New_York'); -- old configurationParameter value: not defined
INSERT INTO user_info."user" ("user_id", "user_name", "user_role") VALUES (1, E'toor', E'super_admin');
SELECT setval(pg_get_serial_sequence('user_info.user', 'user_id'), MAX(user_id), TRUE) FROM user_info.user;
INSERT INTO log."event" ("event_id", "user_id", "event_type", "event_detail") VALUES (20, 1, ('Read'), E'Profile read');
SELECT setval(pg_get_serial_sequence('log.event', 'event_id'), MAX(event_id), TRUE) FROM log.event;

-- NON-STAGED SQL COMMANDS


COMMIT; -- STRIP_SLONY: SlonyI installs/upgrades strip this line, the rest need to keep the install transactional

