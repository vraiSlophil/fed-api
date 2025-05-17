-- ROLES -------------------------------------------------------------------
CREATE TABLE roles (
                       power SMALLINT PRIMARY KEY,
                       name  VARCHAR(50) NOT NULL UNIQUE,
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO roles (power, name) VALUES (10, 'user'), (100, 'admin');

-- USERS -------------------------------------------------------------------
CREATE TABLE users (
                       id BINARY(16) PRIMARY KEY,            -- UUID v7
                       nickname VARCHAR(50) NOT NULL UNIQUE,
                       email  VARCHAR(255) NOT NULL UNIQUE,
                       password VARCHAR(255) NOT NULL,
                       avatar_path VARCHAR(255),
                       last_name  VARCHAR(100),
                       first_name VARCHAR(100),
                       last_login_at DATETIME,
                       last_login_ip VARCHAR(45),
                       settings JSON,
                       role_power SMALLINT DEFAULT 10,
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                       FOREIGN KEY (role_power) REFERENCES roles(power)
);

-- USER LOGINS -------------------------------------------------------------
CREATE TABLE user_logins (
                             id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                             user_id BINARY(16) NOT NULL,
                             logged_at DATETIME NOT NULL,
                             ip VARCHAR(45) NOT NULL,
                             INDEX (user_id, logged_at DESC),
                             FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- THEMES ------------------------------------------------------------------
CREATE TABLE themes (
                        id BINARY(16) PRIMARY KEY,
                        owner_id BINARY(16) NOT NULL,
                        title VARCHAR(150) NOT NULL,
                        color CHAR(7) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX (owner_id),
                        FULLTEXT KEY ft_title (title),
                        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- PERMISSIONS DES INVITÉS --------------------------------------------------
CREATE TABLE theme_user_permissions (
                                        theme_id BINARY(16) NOT NULL,
                                        user_id  BINARY(16) NOT NULL,
                                        can_update_theme  BOOLEAN DEFAULT FALSE,
                                        can_add_task      BOOLEAN DEFAULT FALSE,
                                        can_delete_task   BOOLEAN DEFAULT FALSE,
                                        can_validate_task BOOLEAN DEFAULT FALSE,
                                        status ENUM('active','revoked') DEFAULT 'active',
                                        invited_at DATETIME NOT NULL,
                                        PRIMARY KEY (theme_id, user_id),
                                        INDEX (status),
                                        FOREIGN KEY (theme_id) REFERENCES themes(id) ON DELETE CASCADE,
                                        FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
);

-- TASKS -------------------------------------------------------------------
CREATE TABLE tasks (
                       id BINARY(16) PRIMARY KEY,
                       theme_id   BINARY(16) NOT NULL,
                       creator_id BINARY(16) NULL,
                       title  VARCHAR(255) NOT NULL,
                       status ENUM('todo','doing','done') DEFAULT 'todo',
                       validated_at DATETIME,
                       archived_at DATETIME,
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                       INDEX (theme_id, status, created_at),
                       INDEX (archived_at),
                       FULLTEXT KEY ft_task_title (title),
                       FOREIGN KEY (theme_id)   REFERENCES themes(id) ON DELETE CASCADE,
                       FOREIGN KEY (creator_id) REFERENCES users(id)  ON DELETE SET NULL
);

-- AUDIT LOGS (JSON) -------------------------------------------------------
CREATE TABLE audit_logs (
                            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            auditable_type VARCHAR(100) NOT NULL,
                            auditable_id   BINARY(16)   NOT NULL,
                            changed_at DATETIME NOT NULL,
                            data JSON NOT NULL,
                            INDEX (auditable_type, auditable_id),
                            INDEX idx_action ((data->>'$.meta.action')) -- index virtuel MariaDB
    );

-- STATISTIQUES UTILISATEUR ------------------------------------------------
CREATE TABLE user_metrics (
                              user_id BINARY(16) PRIMARY KEY,
                              tasks_created INT UNSIGNED DEFAULT 0,
                              tasks_done    INT UNSIGNED DEFAULT 0,
                              streak        INT UNSIGNED DEFAULT 0,
                              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
