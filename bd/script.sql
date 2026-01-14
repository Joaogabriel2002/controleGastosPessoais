-- 1. DESTRÓI O BANCO ANTIGO E CRIA O NOVO
DROP DATABASE IF EXISTS gastos_control;
CREATE DATABASE gastos_control CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gastos_control;

-- 2. TABELA DE CATEGORIAS
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);

-- 3. TABELA DE PESSOAS (RESPONSÁVEIS)
CREATE TABLE people (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);

-- 4. TABELA DE CONTAS BANCÁRIAS (SALDO REAL)
CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    current_balance DECIMAL(10, 2) DEFAULT 0.00
);

-- 5. TABELA DE CARTÕES DE CRÉDITO
CREATE TABLE credit_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    closing_day INT NOT NULL, -- Dia que fecha a fatura
    due_day INT NOT NULL      -- Dia que vence a fatura
);

-- 6. TABELA PRINCIPAL DE TRANSAÇÕES
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    type ENUM('entrada', 'saida') NOT NULL,
    status ENUM('pendente', 'pago') DEFAULT 'pendente',
    
    -- Datas Importantes
    due_date DATE NOT NULL,       -- Data de Vencimento
    paid_at DATE NULL,            -- Data do Pagamento Real
    invoice_date DATE NULL,       -- Mês de referência da fatura (sempre dia 01)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Relacionamentos
    category_id INT NULL,
    account_id INT NULL,          -- Se NULL, não mexeu em conta (ex: só cartão pendente)
    credit_card_id INT NULL,      -- Se NULL, é movimentação de conta/dinheiro
    person_id INT NOT NULL DEFAULT 1, -- Importante para lógica de "A Receber"
    
    -- Flag para saber se é um pagamento de fatura (para não somar despesa duplicada em relatórios)
    is_invoice_payment BOOLEAN DEFAULT FALSE,

    -- Chaves Estrangeiras
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (credit_card_id) REFERENCES credit_cards(id) ON DELETE SET NULL,
    FOREIGN KEY (person_id) REFERENCES people(id)
);

-- 7. TABELA DE HISTÓRICO (KARDEX)
-- Registra cada mudança de saldo para auditoria
CREATE TABLE account_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    transaction_id INT NULL, -- Qual transação gerou isso (pode ser NULL se for ajuste manual)
    operation_type ENUM('entrada', 'saida') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    
    previous_balance DECIMAL(10, 2) NOT NULL,
    new_balance DECIMAL(10, 2) NOT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
);

-- =========================================================
-- 8. INSERÇÃO DE DADOS INICIAIS (SEED)
-- Isso evita que o sistema comece vazio e quebre selects
-- =========================================================

-- Inserindo Pessoas (GARANTINDO QUE ID 1 SEJA VOCÊ)
INSERT INTO people (id, name) VALUES 
(1, 'Eu (Pessoal)'),
(2, 'Esposa'),
(3, 'Mãe'),
(4, 'Filhos');

-- Inserindo Categorias Básicas
INSERT INTO categories (name) VALUES 
('Alimentação'), ('Moradia'), ('Transporte'), ('Lazer'), 
('Saúde'), ('Educação'), ('Salário'), ('Investimento'), ('Outros');

-- Inserindo uma Conta Padrão (Carteira)
INSERT INTO accounts (name, current_balance) VALUES 
('Carteira / Dinheiro', 0.00);
-- Se tiver contas reais, cadastre pela tela de configurações depois

-- Inserindo um Exemplo de Cartão
-- INSERT INTO credit_cards (name, closing_day, due_day) VALUES ('Nubank', 1, 8);