# Capability-Context Relationship Diagram

## Overview
This diagram illustrates how capabilities are checked within Moodle's context hierarchy and how the Who Is Who tool identifies conflicts and overlaps.

## Moodle Context Hierarchy

```
                        ┌─────────────────┐
                        │  CONTEXT_SYSTEM │ (Level 10)
                        │   (Site-wide)   │
                        └────────┬────────┘
                                 │
                ┌────────────────┼────────────────┐
                ▼                ▼                ▼
        ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
        │CONTEXT_USER  │ │CONTEXT_COURSE│ │ CONTEXT_     │ (Level 30/40/50)
        │  (Level 30)  │ │ CATEGORY     │ │ COURSECAT    │
        └──────────────┘ │  (Level 40)  │ └──────────────┘
                         └───────┬──────┘
                                 │
                         ┌───────┴──────┐
                         │CONTEXT_COURSE│ (Level 50)
                         └───────┬──────┘
                                 │
                ┌────────────────┼────────────────┐
                ▼                ▼                ▼
        ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
        │CONTEXT_MODULE│ │ CONTEXT_BLOCK│ │Other Course  │ (Level 70/80)
        │  (Level 70)  │ │  (Level 80)  │ │  Contexts    │
        └──────────────┘ └──────────────┘ └──────────────┘
```

## Capability Checking Process

```
┌─────────────────────────────────────────────────────────────────┐
│                    CAPABILITY CHECKING FLOW                      │
└─────────────────────────────────────────────────────────────────┘

1. USER + CONTEXT SELECTION
   ┌──────────────┐        ┌──────────────┐
   │    User ID   │───────▶│   Context    │
   └──────────────┘        └──────┬───────┘
                                   │
                                   ▼
2. ROLE ASSIGNMENTS RETRIEVAL
   ┌─────────────────────────────────────────┐
   │         get_user_roles($context)        │
   │                                         │
   │  Returns: All roles assigned to user    │
   │  in this context                        │
   └────────────────┬────────────────────────┘
                     │
                     ▼
3. CAPABILITY MATRIX BUILDING
   ┌─────────────────────────────────────────┐
   │    For each role assigned to user:      │
   ├─────────────────────────────────────────┤
   │  Role 1 ──▶ [cap1: allow, cap2: prevent]│
   │  Role 2 ──▶ [cap1: allow, cap3: prohibit]│
   │  Role 3 ──▶ [cap2: allow, cap4: allow]  │
   └────────────────┬────────────────────────┘
                     │
                     ▼
4. PARENT CONTEXT INCLUSION (Optional)
   ┌─────────────────────────────────────────┐
   │  if (includeparents == true):           │
   │    - Get parent contexts                │
   │    - Check roles in parent contexts     │
   │    - Merge capability matrix            │
   └────────────────┬────────────────────────┘
                     │
                     ▼
5. ISSUE DETECTION
   ┌─────────────────────────────────────────┐
   │         Analyze Capability Matrix        │
   └────────────────┬────────────────────────┘
                     │
        ┌────────────┼────────────┐
        ▼            ▼            ▼
   ┌─────────┐  ┌─────────┐  ┌─────────┐
   │OVERLAPS │  │CONFLICTS│  │PROHIBIT │
   └─────────┘  └─────────┘  └─────────┘
```

## Capability Issue Types

### 1. Capability Overlaps
```
User has same capability from multiple roles (redundant):

Role A ────▶ capability.view: ALLOW
              ⬇ OVERLAP DETECTED
Role B ────▶ capability.view: ALLOW

Severity: LOW (Level 2)
Impact: Redundancy, potential confusion
```

### 2. Capability Conflicts
```
User has conflicting permissions for same capability:

Role A ────▶ capability.edit: ALLOW
              ⬇ CONFLICT DETECTED  
Role B ────▶ capability.edit: PREVENT

Severity: MEDIUM-HIGH (Level 3-4)
Impact: Unpredictable behavior, security issues
```

### 3. Prohibit Conflicts
```
Most severe - explicit prohibition overrides all:

Role A ────▶ capability.delete: ALLOW
Role B ────▶ capability.delete: ALLOW
              ⬇ PROHIBIT OVERRIDES ALL
Role C ────▶ capability.delete: PROHIBIT ❌

Severity: CRITICAL (Level 4)
Impact: Complete denial regardless of other permissions
```

## Scan Configuration Options

```
┌──────────────────────────────────────────────┐
│            SCAN CONFIGURATION                 │
├──────────────────────────────────────────────┤
│                                              │
│  ┌─────────────────────────────────────┐    │
│  │ Context Scope:                      │    │
│  │  • Root Context ID (optional)       │    │
│  │  • Include Parent Contexts (bool)   │    │
│  │  • Context Levels Filter (array)    │    │
│  └─────────────────────────────────────┘    │
│                                              │
│  ┌─────────────────────────────────────┐    │
│  │ User Scope:                         │    │
│  │  • Specific User IDs (optional)     │    │
│  │  • Or scan all users in context     │    │
│  └─────────────────────────────────────┘    │
│                                              │
│  ┌─────────────────────────────────────┐    │
│  │ Issue Types:                        │    │
│  │  ☑ Scan for Overlaps                │    │
│  │  ☑ Scan for Conflicts               │    │
│  │  ☐ Overlap Only Mode                │    │
│  └─────────────────────────────────────┘    │
│                                              │
└──────────────────────────────────────────────┘
```

## Database Storage Structure

```
┌────────────────────────────────────┐
│     tool_whoiswho_scan             │
├────────────────────────────────────┤
│ id                                 │
│ startedat        ┌──────────────────────────────────────────┐
│ finishedat       │  tool_whoiswho_finding              │
│ status ─────────▶├──────────────────────────────────────────┤
│ initiatedby      │ id                                   │
│ scopecontextid   │ fingerprint (unique)                 │
│ meta (JSON)      │ scanid (FK) ◀────────────────────────┤
└──────────────────┤ type                                 │
                   │ severity                             │
                   │ userid                               │
                   │ contextid                            │
                   │ capability                           │
                   │ firstseenat                          │
                   │ lastseenat                           │
                   │ resolved                             │
                   │ resolvedby                           │
                   │ resolvedat                           │
                   │ details (JSON)                       │
                   └──────────────────────────────────────────┘
                                      │
                   ┌──────────────────────────────────────────┐
                   │  tool_whoiswho_finding_cap          │
                   ├──────────────────────────────────────────┤
                   │ id                                   │
                   │ findingid (FK) ◀─────────────────────┘
                   │ roleid                               │
                   │ permission (CAP_ALLOW/PREVENT/PROHIBIT)│
                   │ capname                              │
                   │ label                                │
                   └──────────────────────────────────────────┘
```

## Context Resolution Example

```
Course Module Context (Assignment)
         │
         ├─▶ Check: User has roles in this module?
         │     └─▶ Yes: Get capabilities from those roles
         │
         ├─▶ Include Parents? 
         │     └─▶ Yes: Check Course Context
         │           │
         │           ├─▶ User has roles in course?
         │           │     └─▶ Yes: Merge capabilities
         │           │
         │           └─▶ Continue up to Category/System
         │
         └─▶ Final Matrix: All capabilities from all contexts
                   │
                   └─▶ Analyze for Issues
```

## Capability Resolution Rules (Moodle Core)

```
Priority Order (Highest to Lowest):
═══════════════════════════════════

1. PROHIBIT  ──────▶  Always wins (blocks everything)
      ↓
2. PREVENT   ──────▶  Blocks unless overridden by ALLOW at same/child level  
      ↓
3. ALLOW     ──────▶  Grants permission
      ↓
4. INHERIT   ──────▶  Checks parent context
      ↓
5. NOT SET   ──────▶  No permission (default deny)
```

## Scan Manager Methods

```
scan_manager::find_capability_issues_for_user()
                        │
    ┌───────────────────┼───────────────────┐
    ▼                   ▼                   ▼
get_user_roles()  Build Cap Matrix   Detect Issues
    │                   │                   │
    └───────────────────┴───────────────────┘
                        │
                        ▼
                 Return Issues Array:
                 {
                   'userid': 123,
                   'contexts': {
                     'contextid': {
                       'overlaps': {...},
                       'conflicts': {...},
                       'stats': {...}
                     }
                   }
                 }
```

## Performance Considerations

```
┌──────────────────────────────────────────────┐
│          PERFORMANCE OPTIMIZATION             │
├──────────────────────────────────────────────┤
│                                              │
│ • Batch Processing:                          │
│   - Process users in chunks                  │
│   - Use recordsets for memory efficiency     │
│                                              │
│ • Context Filtering:                         │
│   - Limit scan to specific context levels    │
│   - Use context path for subtree queries     │
│                                              │
│ • Caching:                                   │
│   - Cache role definitions                   │
│   - Cache capability lookups                 │
│                                              │
│ • Database Optimization:                     │
│   - Indexed searches on context paths        │
│   - Efficient JOIN operations                │
│                                              │
└──────────────────────────────────────────────┘
```