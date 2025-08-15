-- Script para criar a tabela de auditoria de fichas técnicas

CREATE TABLE IF NOT EXISTS ficha_tecnica_auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ficha_tecnica_id INT NOT NULL,
    auditor VARCHAR(100) NOT NULL,
    data_auditoria DATE NOT NULL,
    cozinheiro VARCHAR(100) NOT NULL,
    status_auditoria ENUM('OK', 'NOK') NOT NULL,
    observacoes TEXT,
    periodicidade INT NOT NULL DEFAULT 30 COMMENT 'Periodicidade em dias',
    proxima_auditoria DATE GENERATED ALWAYS AS (DATE_ADD(data_auditoria, INTERVAL periodicidade DAY)) STORED,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ficha_tecnica_id) REFERENCES ficha_tecnica(id) ON DELETE CASCADE
);

-- Índices para melhorar a performance
CREATE INDEX idx_ficha_tecnica_id ON ficha_tecnica_auditoria(ficha_tecnica_id);
CREATE INDEX idx_data_auditoria ON ficha_tecnica_auditoria(data_auditoria);
CREATE INDEX idx_proxima_auditoria ON ficha_tecnica_auditoria(proxima_auditoria);