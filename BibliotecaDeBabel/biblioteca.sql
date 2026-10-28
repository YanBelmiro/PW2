CREATE DATABASE IF NOT EXISTS db_biblioteca_de_babel
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
USE db_biblioteca_de_babel;

-- =========================================
-- NIVEL BASILAR
-- =========================================

-- AUTOR
CREATE TABLE IF NOT EXISTS tbl_autor (
    id_autor INT AUTO_INCREMENT,
    nome_autor VARCHAR(100) NOT NULL,
    nacionalidade VARCHAR(50),
    PRIMARY KEY (id_autor)
) ENGINE=InnoDB;

-- LIVRO
CREATE TABLE IF NOT EXISTS tbl_livro (
    id_livro INT AUTO_INCREMENT,
    titulo VARCHAR(150) NOT NULL,
    ano_publicacao INT,
    id_autor INT NOT NULL,
    gerado_aleatoriamente BOOLEAN DEFAULT FALSE, -- Diferencial: Biblioteca de Babel
    conteudo_gerado TEXT,                         -- Armazena o texto gerado proceduralmente (se aplicável)
    PRIMARY KEY (id_livro),
    FOREIGN KEY (id_autor) REFERENCES tbl_autor(id_autor)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================================
-- A ESCÓRIA
-- =========================================

-- USUÁRIO
CREATE TABLE IF NOT EXISTS tbl_usuario (
    id_usuario INT AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo_usuario ENUM('leitor', 'admin') DEFAULT 'leitor',
    PRIMARY KEY (id_usuario)
) ENGINE=InnoDB;

-- EMPRÉSTIMO
CREATE TABLE IF NOT EXISTS tbl_emprestimo (
    id_emprestimo INT AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    id_livro INT NOT NULL,
    data_emprestimo DATE NOT NULL,
    data_devolucao_prevista DATE NOT NULL,
    data_devolucao_real DATE,
    status ENUM('ativo', 'devolvido', 'atrasado') DEFAULT 'ativo',
    PRIMARY KEY (id_emprestimo),
    FOREIGN KEY (id_usuario) REFERENCES tbl_usuario(id_usuario)
    ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_livro) REFERENCES tbl_livro(id_livro)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- MULTA
CREATE TABLE IF NOT EXISTS tbl_multa (
    id_multa INT AUTO_INCREMENT,
    id_emprestimo INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    status_multa ENUM('pendente', 'paga') DEFAULT 'pendente',
    PRIMARY KEY (id_multa),
    FOREIGN KEY (id_emprestimo) REFERENCES tbl_emprestimo(id_emprestimo)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;