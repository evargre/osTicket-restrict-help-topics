-- Tabla de acceso a temas por usuario (prefijo ost_ según ost-config.php)
CREATE TABLE IF NOT EXISTS `ost_user_topic_access` (
  `user_id` int(10) unsigned NOT NULL,
  `topic_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_id`, `topic_id`),
  KEY `topic_id` (`topic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
