--  База данных: web4sem
--  Пользователь: webuser

CREATE TABLE IF NOT EXISTS language (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO language (name) VALUES
  ('Pascal'),
  ('C'),
  ('C++'),
  ('JavaScript'),
  ('PHP'),
  ('Python'),
  ('Java'),
  ('Haskell'),
  ('Clojure'),
  ('Prolog'),
  ('Scala'),
  ('Go');

CREATE TABLE IF NOT EXISTS application (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(150) NOT NULL DEFAULT '',
  phone varchar(20) NOT NULL DEFAULT '',
  email char(128) NOT NULL DEFAULT '',
  birthdate date NOT NULL,
  gender varchar(10) NOT NULL DEFAULT '',
  bio text,
  contract tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS application_language (
  app_id int(10) unsigned NOT NULL,
  lang_id int(10) unsigned NOT NULL,
  PRIMARY KEY (app_id, lang_id),
  FOREIGN KEY (app_id) REFERENCES application(id) ON DELETE CASCADE,
  FOREIGN KEY (lang_id) REFERENCES language(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
