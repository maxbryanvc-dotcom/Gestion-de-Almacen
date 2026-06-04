-- ============================================================
-- CATÁLOGO COMPLETO DE MATERIALES — ElectroSur Este
-- Ejecutar en phpMyAdmin sobre almacen_sistema
-- ============================================================

-- Insertar materiales del catálogo oficial (ignora si ya existen)
INSERT IGNORE INTO `materiales` (`codigo`, `codigo_electrosur`, `nombre`, `unidad`, `stock`, `activo`) VALUES
('314720', '314720', 'CABLE CONCENTRICO D/ AL 2X16MM2',              'Metro',  0, 1),
('305559', '305559', 'CABLE CONCENTRICO D/ AL 2X6MM2',               'Metro',  0, 1),
('314721', '314721', 'CABLE CONCENTRICO D/ AL 3X16MM2',              'Metro',  0, 1),
('317126', '317126', 'CABLE CONCENTRICO D/ AL 4X16MM2',              'Metro',  0, 1),
('304249', '304249', 'CAJA PORTAMEDIDOR 1F POLIMERICO',              'Unidad', 0, 1),
('309778', '309778', 'CAJATOMA METALICA ELSE I',                     'Unidad', 0, 1),
('306370', '306370', 'CAJATOMA METALICA TRIFASICA ESTANDAR',         'Unidad', 0, 1),
('321682', '321682', 'CONECTOR T/PERFORACION AL/AL 120/16 mm2',      'Unidad', 0, 1),
('321684', '321684', 'CONECTOR T/PERFORACION AL/CU 120/16 mm2',      'Unidad', 0, 1),
('307446', '307446', 'INTERRUP. AUT. CURVA C 3X63A 10KA/380V',       'Unidad', 0, 1),
('319212', '319212', 'INTERRUP. AUTOM. CURVA C 2P 16A 3KA/220V',     'Unidad', 0, 1),
('319213', '319213', 'INTERRUP. AUTOM. CURVA C 2P 50A 3KA/220V',     'Unidad', 0, 1),
('319215', '319215', 'INTERRUP. AUTOM. CURVA C 3P 63A 6KA/380V',     'Unidad', 0, 1),
('307799', '307799', 'MEDIDOR ELECTRONICO 3F 3H',                    'Unidad', 0, 1),
('304754', '304754', 'MEDIDOR ELECTRONICO 3F 4H',                    'Unidad', 0, 1),
('309896', '309896', 'MEDIDOR ELECTRONICO 1F 2H',                    'Unidad', 0, 1),
('321225', '321225', 'MEDIDOR 3F INDR ELCTRNC 0.2 10A',              'Unidad', 0, 1),
('308555', '308555', 'PRECINTO DE SEGURIDAD P/CAJATOMA',             'Unidad', 0, 1),
('318981', '318981', 'PRECINTO POLIC. TRANSPARENTE P/MEDIDOR',       'Unidad', 0, 1),
('308764', '308764', 'SEPARADOR DE LINEA PVC-SAP 5 VIAS 1"',         'Unidad', 0, 1),
('319389', '319389', 'TEMPLADOR F°G° P/ACOMETIDA DOMICIL. 3F',       'Unidad', 0, 1),
('309973', '309973', 'TEMPLADOR ACOMETIDA DOMICILIARIA',             'Unidad', 0, 1),
('316292', '316292', 'TUBO BASTON PVC SAP 1 1/4" X 2.5 M',           'Unidad', 0, 1),
('309416', '309416', 'TUBO BASTON PVC-SAP 1"X2.5M',                  'Unidad', 0, 1),
('303530', '303530', 'TUBO FLEXIBLE 3/4"',                           'Metro',  0, 1),
('309440', '309440', 'TUBO FLEXIBLE TIPO TAM 1"',                    'Metro',  0, 1);

-- Actualizar código_electrosur en materiales que ya existen con código correcto
UPDATE `materiales` SET `codigo_electrosur` = `codigo`
WHERE `codigo_electrosur` = '' AND `codigo` REGEXP '^[0-9]+$';
