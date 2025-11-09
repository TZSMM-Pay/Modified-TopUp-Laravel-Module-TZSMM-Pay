
# 💳 Modified TopUp Laravel Module — TZSMM Pay Integration

This repository contains a Laravel module designed to integrate the **TZSMM Pay** payment gateway into your **TopUp System**.  
It enables users to make automated deposits and payments through the TZSMM Pay API with full backend support and easy configuration.

---

## 🧩 Overview

**Module Name:** Modified-TopUp-Laravel-Module-TZSMM-Pay  
**Purpose:** Add TZSMM Pay as a secure and automated payment option to your Laravel TopUp platform.

---

## 🚀 Features

- 🔌 Seamless integration with your existing Laravel TopUp system  
- ⚙️ Configurable from the Filament admin panel (Settings page)  
- 🔄 Automated payment verification and callback support  
- 💰 Real-time transaction updates  
- 🧱 Modular structure for simple customization  
- 🧾 Includes ready-to-import `db.sql` file for required database structure

---

## 📂 Folder Structure

```

Modified-TopUp-Laravel-Module-TZSMM-Pay/
│
├── app/
│   └── Filament/
│       └── Pages/
│           └── Settings.php              # Filament admin settings for TZSMM Pay
│
├── core/
│   └── GeneralSettings.php               # Core gateway settings handler
│
├── db.sql                                # SQL structure for necessary tables
│
└── README.md                             # Project documentation

````

---

## ⚙️ Installation

1. **Clone this repository** into your Laravel project:
   ```bash
   git clone https://github.com/TZSMM-Pay/Modified-TopUp-Laravel-Module-TZSMM-Pay.git
   ```

2. **Import the database file:**

   ```bash
   mysql -u username -p database_name < db.sql
   ```


4. **Configure your API credentials** via Filament admin panel:

   * Go to **Filament → Settings → TZSMM Pay**
   * Set:

     * `API Key`

5. **Link your payment logic** to use the module’s methods or service layer.

---

## 🔗 API Documentation

Full API documentation for TZSMM Pay can be found at:
👉 [https://tzsmmpay.com/docs](https://tzsmmpay.com/docs)

---

## 🧪 Testing

* Use sandbox/test credentials from your TZSMM Pay dashboard.
* Check your Laravel `storage/logs/` directory for transaction responses.
* Validate callback responses via your configured endpoint.

---

## 🧑‍💻 Author

**TZSMM Pay Team**
📧 [support@tzsmm.com](mailto:info@tzsmmpay.com)
🌐 [https://tzsmmpay.com](https://tzsmmpay.com)

---

## 📝 License

This project is licensed under the **MIT License**.
You are free to modify and integrate it within your Laravel-based TopUp system.

---

> ⚠️ **Note:** This module requires **Laravel 9+** and **Filament v3+**.


