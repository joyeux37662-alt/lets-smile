USE lets_smile;

SET @cabinet_id := (SELECT id FROM cabinets WHERE slug = 'lets-smile' LIMIT 1);
SET @user_id := (SELECT id FROM users WHERE email = 'admin@letssmile.mg' LIMIT 1);
SET @cat_consumables := (SELECT id FROM stock_categories WHERE cabinet_id = @cabinet_id ORDER BY id ASC LIMIT 1 OFFSET 0);
SET @cat_meds := (SELECT id FROM stock_categories WHERE cabinet_id = @cabinet_id ORDER BY id ASC LIMIT 1 OFFSET 1);
SET @cat_materials := (SELECT id FROM stock_categories WHERE cabinet_id = @cabinet_id ORDER BY id ASC LIMIT 1 OFFSET 2);
SET @cat_equipment := (SELECT id FROM stock_categories WHERE cabinet_id = @cabinet_id ORDER BY id ASC LIMIT 1 OFFSET 3);

INSERT INTO suppliers (cabinet_id, name, phone, email, address)
SELECT @cabinet_id, 'Henry Schein Madagascar', '+261 34 11 222 33', 'contact@henryschein.mg', 'Ankorondrano, Antananarivo'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE cabinet_id = @cabinet_id AND name = 'Henry Schein Madagascar');

INSERT INTO suppliers (cabinet_id, name, phone, email, address)
SELECT @cabinet_id, 'Dental Pro Madagascar', '+261 33 44 555 66', 'vente@dentalpro.mg', 'Ivandry, Antananarivo'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE cabinet_id = @cabinet_id AND name = 'Dental Pro Madagascar');

INSERT INTO suppliers (cabinet_id, name, phone, email, address)
SELECT @cabinet_id, 'Medident Ocean Indien', '+261 32 77 888 99', 'commande@medident.mg', 'Analakely, Antananarivo'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE cabinet_id = @cabinet_id AND name = 'Medident Ocean Indien');

SET @supplier_henry := (SELECT id FROM suppliers WHERE cabinet_id = @cabinet_id AND name = 'Henry Schein Madagascar' LIMIT 1);
SET @supplier_dental := (SELECT id FROM suppliers WHERE cabinet_id = @cabinet_id AND name = 'Dental Pro Madagascar' LIMIT 1);
SET @supplier_medident := (SELECT id FROM suppliers WHERE cabinet_id = @cabinet_id AND name = 'Medident Ocean Indien' LIMIT 1);

INSERT INTO products
  (cabinet_id, category_id, supplier_id, name, reference, barcode, quantity, minimum_quantity, unit, unit_price, image_path)
SELECT @cabinet_id, data.category_id, data.supplier_id, data.name, data.reference, data.barcode, data.quantity, data.minimum_quantity, data.unit, data.unit_price, NULL
FROM (
  SELECT @cat_consumables category_id, @supplier_henry supplier_id, 'Gants nitrile (boite de 100)' name, 'GNT-NIT-100' reference, '3760123456789' barcode, 24 quantity, 10 minimum_quantity, 'boites' unit, 6900 unit_price
  UNION ALL SELECT @cat_meds, @supplier_medident, 'Anesthesique Ubistesin 1/100', 'UBI-1-100', '3760123456790', 8, 10, 'boites', 29500
  UNION ALL SELECT @cat_consumables, @supplier_henry, 'Aiguilles dentaires 27G (boite de 100)', 'AIG-27G', '3760123456791', 0, 10, 'boites', 12500
  UNION ALL SELECT @cat_materials, @supplier_dental, 'Composite Filtek Z250', 'FILTEK-Z250', '3760123456792', 15, 5, 'seringues', 85000
  UNION ALL SELECT @cat_consumables, @supplier_henry, 'Digue dentaire (6x6)', 'DIGUE-6X6', '3760123456793', 32, 15, 'feuilles', 1200
  UNION ALL SELECT @cat_consumables, @supplier_dental, 'Embouts melangeurs bleus', 'EMB-BLEU', '3760123456794', 6, 10, 'sachets', 18900
  UNION ALL SELECT @cat_materials, @supplier_medident, 'Ciment verre ionomere', 'CVI-CIMENT', '3760123456795', 4, 5, 'pots', 45000
  UNION ALL SELECT @cat_consumables, @supplier_henry, 'Bicarbonate de sodium', 'BICAR-500', '3760123456796', 20, 10, 'flacons', 4500
) data
WHERE NOT EXISTS (
  SELECT 1 FROM products p WHERE p.cabinet_id = @cabinet_id AND p.reference = data.reference
);

INSERT INTO stock_movements (product_id, user_id, type, quantity, reason, created_at)
SELECT p.id, @user_id, data.type, data.quantity, data.reason, data.created_at
FROM (
  SELECT 'GNT-NIT-100' reference, 'in' type, 10 quantity, 'Reapprovisionnement fournisseur' reason, '2026-04-22 09:30:00' created_at
  UNION ALL SELECT 'GNT-NIT-100', 'out', 3, 'Utilisation clinique', '2026-04-15 16:10:00'
  UNION ALL SELECT 'GNT-NIT-100', 'in', 20, 'Entrée de stock initiale', '2026-04-02 08:50:00'
  UNION ALL SELECT 'UBI-1-100', 'out', 2, 'Soins implantaires', '2026-04-20 11:15:00'
  UNION ALL SELECT 'AIG-27G', 'out', 5, 'Rupture après utilisation', '2026-04-18 14:20:00'
  UNION ALL SELECT 'FILTEK-Z250', 'in', 8, 'Livraison mensuelle', '2026-04-17 10:00:00'
  UNION ALL SELECT 'EMB-BLEU', 'out', 4, 'Prothèse et empreinte', '2026-04-12 15:40:00'
  UNION ALL SELECT 'CVI-CIMENT', 'adjustment', 4, 'Correction inventaire', '2026-04-10 13:05:00'
) data
INNER JOIN products p ON p.cabinet_id = @cabinet_id AND p.reference = data.reference
WHERE NOT EXISTS (
  SELECT 1
  FROM stock_movements sm
  WHERE sm.product_id = p.id
    AND sm.reason = data.reason
    AND sm.created_at = data.created_at
);
