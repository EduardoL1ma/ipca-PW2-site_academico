-- ============================================================
-- Sistema de Gestão Académica — IPCA
-- Script SQL completo de criação da base de dados
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Criar base de dados
-- ------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `ipca` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `ipca`;

-- ------------------------------------------------------------
-- Tabela: grupos
-- ------------------------------------------------------------
CREATE TABLE `grupos` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `GRUPO` varchar(20) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `grupos` (`ID`, `GRUPO`) VALUES
(1, 'ADMIN'),
(2, 'ALUNO'),
(3, 'FUNCIONARIO'),
(4, 'GESTOR');

-- ------------------------------------------------------------
-- Tabela: users
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `login` varchar(20) NOT NULL,
  `pwd` varchar(255) NOT NULL,
  `grupo` int(11) NOT NULL,
  PRIMARY KEY (`login`),
  FOREIGN KEY (`grupo`) REFERENCES `grupos`(`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Passwords: todas são 'ola' em bcrypt
-- Para gerar novo hash: aceder a http://localhost/CURSOS/gerar_hash.php
INSERT INTO `users` (`login`, `pwd`, `grupo`) VALUES
('admin1',       '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 1),
('aluno1',       '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 2),
('funcionario1', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 3),
('gestor1_ped',  '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 4);

-- IMPORTANTE: O hash acima pode não funcionar em todos os servidores.
-- Se o login falhar, acede a http://localhost/CURSOS/teste_login.php
-- para gerar e atualizar o hash correto para o teu servidor.

-- ------------------------------------------------------------
-- Tabela: cursos
-- ------------------------------------------------------------
CREATE TABLE `cursos` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Nome` text NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cursos` (`ID`, `Nome`, `ativo`) VALUES
(1, 'Desenvolvimento Web e Multimédia', 1),
(2, 'Comércio Eletrónico', 1),
(3, 'Redes de Computadores', 1);

-- ------------------------------------------------------------
-- Tabela: disciplinas
-- ------------------------------------------------------------
CREATE TABLE `disciplinas` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Nome_disc` text NOT NULL,
  `codigo` varchar(10) DEFAULT NULL,
  `ects` int(11) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `disciplinas` (`ID`, `Nome_disc`, `codigo`, `ects`, `ativo`) VALUES
(1, 'Matemática',              'MAT', 6, 1),
(2, 'Programação WEB I',       'PW1', 6, 1),
(3, 'Linguagens de Programação','LP', 6, 1),
(4, 'Português',               'PT',  4, 1);

-- ------------------------------------------------------------
-- Tabela: plano_estudos
-- ------------------------------------------------------------
CREATE TABLE `plano_estudos` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `CURSOS` int(11) NOT NULL,
  `DISCIPLINA` int(11) NOT NULL,
  `ano` int(11) NOT NULL DEFAULT 1,
  `semestre` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `unico` (`CURSOS`, `DISCIPLINA`, `ano`, `semestre`),
  FOREIGN KEY (`CURSOS`) REFERENCES `cursos`(`ID`),
  FOREIGN KEY (`DISCIPLINA`) REFERENCES `disciplinas`(`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `plano_estudos` (`CURSOS`, `DISCIPLINA`, `ano`, `semestre`) VALUES
(1, 1, 1, 1),
(1, 2, 1, 1),
(1, 3, 1, 2),
(1, 4, 1, 2);

-- ------------------------------------------------------------
-- Tabela: ficha_aluno
-- ------------------------------------------------------------
CREATE TABLE `ficha_aluno` (
  `login` varchar(20) NOT NULL,
  `nome_completo` varchar(100) NOT NULL,
  `data_nascimento` date NOT NULL,
  `morada` varchar(200) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `nif` varchar(9) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'rascunho',
  `observacoes` text DEFAULT NULL,
  `data_submissao` datetime DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `curso_pretendido` int(11) DEFAULT NULL,
  `decidido_por` varchar(20) DEFAULT NULL,
  `data_decisao` datetime DEFAULT NULL,
  PRIMARY KEY (`login`),
  FOREIGN KEY (`login`) REFERENCES `users`(`login`) ON UPDATE CASCADE,
  FOREIGN KEY (`curso_pretendido`) REFERENCES `cursos`(`ID`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabela: matriculas
-- ------------------------------------------------------------
CREATE TABLE `matriculas` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(20) NOT NULL,
  `curso` int(11) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pendente',
  `data_pedido` datetime DEFAULT CURRENT_TIMESTAMP,
  `observacoes` text DEFAULT NULL,
  `decidido_por` varchar(20) DEFAULT NULL,
  `data_decisao` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  FOREIGN KEY (`login`) REFERENCES `users`(`login`) ON UPDATE CASCADE,
  FOREIGN KEY (`curso`) REFERENCES `cursos`(`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabela: pautas_header
-- ------------------------------------------------------------
CREATE TABLE `pautas_header` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `curso` int(11) NOT NULL,
  `disciplina` int(11) NOT NULL,
  `ano_letivo` varchar(9) NOT NULL,
  `epoca` varchar(20) NOT NULL,
  `criado_por` varchar(20) NOT NULL,
  `data_criacao` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `unica` (`curso`, `disciplina`, `ano_letivo`, `epoca`),
  FOREIGN KEY (`curso`) REFERENCES `cursos`(`ID`),
  FOREIGN KEY (`disciplina`) REFERENCES `disciplinas`(`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabela: pautas
-- ------------------------------------------------------------
CREATE TABLE `pautas` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `pauta_id` int(11) DEFAULT NULL,
  `curso` int(11) NOT NULL,
  `disciplina` int(11) NOT NULL,
  `login_aluno` varchar(20) NOT NULL,
  `nota` decimal(4,1) DEFAULT NULL,
  `data_registo` datetime DEFAULT NULL,
  `ano_letivo` varchar(9) NOT NULL DEFAULT '2025/2026',
  `epoca` varchar(20) NOT NULL DEFAULT 'Normal',
  PRIMARY KEY (`ID`),
  FOREIGN KEY (`pauta_id`) REFERENCES `pautas_header`(`ID`),
  FOREIGN KEY (`curso`) REFERENCES `cursos`(`ID`),
  FOREIGN KEY (`disciplina`) REFERENCES `disciplinas`(`ID`),
  FOREIGN KEY (`login_aluno`) REFERENCES `users`(`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
