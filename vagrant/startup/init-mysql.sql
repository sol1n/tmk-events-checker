CREATE USER 'baihe'@'localhost' IDENTIFIED BY 'baihe';
CREATE DATABASE baihe CHARACTER SET utf8 COLLATE utf8_general_ci;
GRANT ALL PRIVILEGES ON baihe.* TO 'baihe'@'localhost';
FLUSH PRIVILEGES;