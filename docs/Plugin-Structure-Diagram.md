# Moodle Tool Who Is Who - Plugin Structure Diagram

## Overview
Security scanning tool for Moodle LMS to identify capability issues and potential security problems.

## Directory Structure

```
moodle-tool_whoiswho/
│
├── 📁 classes/                    # Core PHP classes
│   ├── 📁 form/                   # Moodle forms
│   │   └── issues_filter_form.php # Filter form for issues page
│   │
│   ├── 📁 local/                  # Local utility classes
│   │   ├── 📁 manager/            # Manager classes
│   │   │   └── capability_manager.php # Capability management
│   │   ├── 📁 scanner/            # Scanner implementations
│   │   │   ├── base_scanner.php   # Abstract base scanner class
│   │   │   └── capability_issue_scanner.php # Capability scanner
│   │   └── scan_manager.php       # Manages security scans
│   │
│   ├── 📁 output/                 # Output/renderer classes
│   │   └── dashboard.php          # Dashboard rendering
│   │
│   ├── 📁 table/                  # Table classes
│   │   ├── issues_table.php       # Issues display table
│   │   └── users_overview_table.php # Users overview table
│   │
│   ├── 📁 task/                   # Scheduled tasks
│   │   └── scan_scheduled.php     # Automated scanning task
│   │
│   └── helper.php                 # General helper functions
│
├── 📁 db/                         # Database and configuration
│   ├── access.php                 # Capability definitions
│   ├── install.xml                # Database schema
│   ├── tasks.php                  # Task definitions
│   └── upgrade.php                # Upgrade scripts
│
├── 📁 lang/                       # Language strings
│   └── 📁 en/
│       └── tool_whoiswho.php      # English translations
│
├── 📁 templates/                  # Mustache templates
│   └── dashboard.mustache         # Dashboard template
│
├── 📁 tests/                      # Unit tests
│   └── scan_manager_test.php      # Scan manager tests
│
├── 📁 view/                       # View pages
│   ├── dashboard.php              # Dashboard entry point
│   ├── issues.php                 # Issues listing page
│   └── users.php                  # Users overview page
│
├── settings.php                   # Admin settings
└── version.php                    # Plugin version info
```

## Component Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      User Interface Layer                    │
├─────────────────────────────────────────────────────────────┤
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐ │
│  │  Dashboard   │    │   Issues     │    │   Settings   │ │
│  │   (view/)    │    │   (view/)    │    │(settings.php)│ │
│  └──────┬───────┘    └──────┬───────┘    └──────────────┘ │
└─────────┴───────────────────┴──────────────────────────────┘
          │                   │
          ▼                   ▼
┌─────────────────────────────────────────────────────────────┐
│                    Rendering/Output Layer                    │
├─────────────────────────────────────────────────────────────┤
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐ │
│  │  Dashboard   │    │    Issues    │    │    Forms     │ │
│  │   Renderer   │    │    Table     │    │   (Filter)   │ │
│  │(output/dash.)│    │(table/issues)│    │(form/issues) │ │
│  └──────┬───────┘    └──────┬───────┘    └──────────────┘ │
└─────────┴───────────────────┴──────────────────────────────┘
          │                   │
          ▼                   ▼
┌─────────────────────────────────────────────────────────────┐
│                     Business Logic Layer                     │
├─────────────────────────────────────────────────────────────┤
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐ │
│  │    Scan      │    │   Scanner    │    │   Helper     │ │
│  │   Manager    │───▶│   Classes    │    │  Functions   │ │
│  │(local/scan)  │    │(local/scanner)│    │ (helper.php) │ │
│  └──────────────┘    └──────────────┘    └──────────────┘ │
│         ▲                   │                               │
│         │                   ▼                               │
│  ┌──────────────┐    ┌──────────────┐                     │
│  │  Capability  │    │     Base     │                     │
│  │   Manager    │    │   Scanner    │                     │
│  │(local/manager)│    │(local/scanner)│                     │
│  └──────────────┘    └──────────────┘                     │
│         ▲                                                   │
│         │                                                   │
│  ┌──────────────┐                                          │
│  │  Scheduled   │                                          │
│  │    Task      │                                          │
│  │(task/scan)   │                                          │
│  └──────────────┘                                          │
└─────────────────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────────────────┐
│                      Database Layer                          │
├─────────────────────────────────────────────────────────────┤
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐ │
│  │   Install    │    │   Upgrade    │    │   Access     │ │
│  │   Schema     │    │   Scripts    │    │ Capabilities │ │
│  │(db/install)  │    │(db/upgrade)  │    │(db/access)   │ │
│  └──────────────┘    └──────────────┘    └──────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

## Data Flow

```
User Request → View Page → Output Renderer → Scan Manager
                                                    │
                                                    ▼
                                            Scanner Classes
                                                    │
                                                    ▼
                                            Security Checks
                                                    │
                                                    ▼
                                            Results Storage
                                                    │
                                                    ▼
                                            Table Display ← User
```

## Key Components

### Scanners (`classes/local/scanner/`)
- **base_scanner.php**: Abstract base class defining scanner interface
- **capability_issue_scanner.php**: Implements capability security checks

### Management (`classes/local/`)
- **scan_manager.php**: Orchestrates security scans, manages scanner instances
- **manager/capability_manager.php**: Manages capability-related operations

### User Interface (`view/`)
- **dashboard.php**: Main entry point, overview of security status
- **issues.php**: Detailed listing of found security issues
- **users.php**: Users overview and management page

### Automation (`classes/task/`)
- **scan_scheduled.php**: Automated background scanning via Moodle's task API

### Configuration (`db/`)
- **access.php**: Defines plugin capabilities (view reports, run scans)
- **tasks.php**: Registers scheduled tasks
- **install.xml**: Database tables for storing scan results

## Plugin Type
This is an **admin tool** plugin (`tool_whoiswho`), installed in:
```
/admin/tool/whoiswho/
```

## Key Features
1. **Security Scanning**: Automated detection of capability issues
2. **Dashboard View**: Overview of security status
3. **Issues Management**: Detailed listing and filtering of issues
4. **Scheduled Scans**: Automated background security checks
5. **Extensible Architecture**: Base scanner class for adding new scanners