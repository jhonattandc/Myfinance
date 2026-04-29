DROP DATABASE IF EXISTS finanzas;
CREATE DATABASE finanzas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE finanzas;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  color VARCHAR(7) NOT NULL,
  icono VARCHAR(10) NOT NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_categorias_usuario FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE obligaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT NULL,
  monto_total DECIMAL(15,2) NOT NULL,
  monto_pagado DECIMAL(15,2) NOT NULL DEFAULT 0,
  fecha_inicio DATE NOT NULL,
  fecha_limite DATE NULL,
  estado ENUM('activa', 'pagada', 'congelada') NOT NULL DEFAULT 'activa',
  color VARCHAR(7) NOT NULL DEFAULT '#63b3ed',
  icono VARCHAR(10) NOT NULL DEFAULT '🏦',
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_obligaciones_usuario FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE ingresos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  descripcion VARCHAR(200) NOT NULL,
  monto DECIMAL(15,2) NOT NULL,
  fecha DATE NOT NULL,
  notas TEXT NULL,
  cartera_id INT NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ingresos_usuario FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE cartera (
  id INT AUTO_INCREMENT PRIMARY KEY,
  persona VARCHAR(150) NOT NULL,
  concepto VARCHAR(200) NOT NULL,
  monto_total DECIMAL(15,2) NOT NULL,
  monto_cobrado DECIMAL(15,2) NOT NULL DEFAULT 0,
  fecha_prestamo DATE NOT NULL,
  fecha_limite DATE NULL,
  estado ENUM('pendiente', 'cobrada', 'vencida') NOT NULL DEFAULT 'pendiente',
  notas TEXT NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cartera_usuario FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

ALTER TABLE ingresos
ADD CONSTRAINT fk_ingresos_cartera FOREIGN KEY (cartera_id) REFERENCES cartera(id) ON DELETE SET NULL;

CREATE TABLE gastos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  descripcion VARCHAR(200) NOT NULL,
  monto DECIMAL(15,2) NOT NULL,
  fecha DATE NOT NULL,
  categoria_id INT NOT NULL,
  obligacion_id INT NULL,
  es_pago_obligacion TINYINT(1) NOT NULL DEFAULT 0,
  notas TEXT NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gastos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  CONSTRAINT fk_gastos_obligacion FOREIGN KEY (obligacion_id) REFERENCES obligaciones(id) ON DELETE SET NULL,
  CONSTRAINT fk_gastos_usuario FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

DELIMITER $$

CREATE PROCEDURE recalcular_obligacion(IN p_obligacion_id INT, IN p_user_id INT)
BEGIN
  DECLARE total_pagado DECIMAL(15,2) DEFAULT 0;
  DECLARE total_deuda DECIMAL(15,2) DEFAULT 0;
  DECLARE estado_actual VARCHAR(20);

  SELECT COALESCE(SUM(monto), 0)
  INTO total_pagado
  FROM gastos
  WHERE obligacion_id = p_obligacion_id
    AND user_id = p_user_id
    AND es_pago_obligacion = 1;

  SELECT monto_total, estado
  INTO total_deuda, estado_actual
  FROM obligaciones
  WHERE id = p_obligacion_id
    AND user_id = p_user_id;

  UPDATE obligaciones
  SET monto_pagado = total_pagado,
      estado = CASE
        WHEN total_pagado >= total_deuda THEN 'pagada'
        WHEN estado_actual = 'pagada' AND total_pagado < total_deuda THEN 'activa'
        ELSE estado
      END
  WHERE id = p_obligacion_id
    AND user_id = p_user_id;
END$$

CREATE TRIGGER after_gasto_insert
AFTER INSERT ON gastos
FOR EACH ROW
BEGIN
  IF NEW.es_pago_obligacion = 1 AND NEW.obligacion_id IS NOT NULL THEN
    CALL recalcular_obligacion(NEW.obligacion_id, NEW.user_id);
  END IF;
END$$

CREATE TRIGGER after_gasto_update
AFTER UPDATE ON gastos
FOR EACH ROW
BEGIN
  IF OLD.es_pago_obligacion = 1 AND OLD.obligacion_id IS NOT NULL THEN
    CALL recalcular_obligacion(OLD.obligacion_id, OLD.user_id);
  END IF;

  IF NEW.es_pago_obligacion = 1 AND NEW.obligacion_id IS NOT NULL THEN
    CALL recalcular_obligacion(NEW.obligacion_id, NEW.user_id);
  END IF;
END$$

CREATE TRIGGER after_gasto_delete
AFTER DELETE ON gastos
FOR EACH ROW
BEGIN
  IF OLD.es_pago_obligacion = 1 AND OLD.obligacion_id IS NOT NULL THEN
    CALL recalcular_obligacion(OLD.obligacion_id, OLD.user_id);
  END IF;
END$$

DELIMITER ;

INSERT INTO usuarios (nombre, email, password_hash) VALUES
('WanDu', 'wandu@demo.com', '$2y$10$Ba2OSuzjOYvXm.sVeWi90.NqiefNOR.fcojrWg4DCumAUT7Z2CZI2');
