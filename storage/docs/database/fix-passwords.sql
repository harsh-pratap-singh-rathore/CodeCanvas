USE codecanvas;
UPDATE users SET password_hash = '$2y$10$yo81UQIah6rDtJywg0TEmeLyfdjxscfbxb/lze/yjb5s8HwMIn5qC' WHERE email = 'admin@codecanvas.com';
UPDATE users SET password_hash = '$2y$10$1QaY7MEuTaR3A0en1IfudOpvsDwPaKtPdtdhdlkXZ/TG3Ul69Xe3G' WHERE email = 'user@codecanvas.com';
