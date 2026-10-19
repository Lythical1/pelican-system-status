# System Status

An admin page for Pelican Panel that shows live usage of the panel server and every node on one screen.

## Features

- **Panel server** - CPU (load per core), memory, swap and disk, with hostname, OS, PHP version, CPU model, uptime, last reboot, process count and kernel
- **Every node** - live CPU, memory, swap and disk from Wings, plus OS, architecture, kernel and Wings version
- **Allocation bars** - how much CPU, memory and disk is allocated to servers compared with the node's limits (starts blinking red if allocation is over 100%)
- **Load chart** - load average for the last 15 minutes, refreshed every 10 seconds
- **Node status** - online/offline dot, the connection error when a node is down, and Maintenance and Deploy badges
- **Color-coded bars** - green below 70%, yellow from 70%, red from 90%

## Installation

1. Download `system-status.zip` from the [latest release](https://github.com/Lythical1/pelican-system-status/releases/latest)
2. Import it under **Admin → Plugins**
3. Open **Admin → System status**

## Requirements

- Admins need permission to view nodes to see the page

## License

[GPL-3.0](LICENSE)
