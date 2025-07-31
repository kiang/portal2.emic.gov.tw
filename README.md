# EMIC Disaster Response Data Processing & Visualization

This project provides automated data processing, analysis, and visualization for Taiwan's Emergency Medical Information Center (EMIC) disaster response data.

## Features

### Data Processing
- **Automated Data Fetching**: Regularly retrieves latest disaster data from EMIC API
- **Intelligent Summary Generation**: Automatically analyzes disaster trends and generates statistical reports
- **Multiple Output Formats**: Supports JSON, HTML, CSV, and other formats

### Visualization Interface
- **Real-time Disaster Map**: Interactive map interface based on Leaflet.js
- **Statistical Reports**: Detailed disaster analysis reports with charts
- **Historical Event Archives**: Complete records of major disaster events

### Data Analysis
- **Trend Analysis**: Tracks changes in disaster case counts
- **Regional Statistics**: Statistics by county and disaster type
- **Time Series**: Tracks case count changes through Git commit history

## Project Structure

```
├── scripts/
│   ├── 01_fetch.php        # Data fetching script
│   ├── 02_summary.php      # Summary generation script  
│   ├── 03_scan.php         # Git history analysis script
│   └── cron.php           # Scheduled task script
├── docs/
│   ├── cases.json         # Disaster case data
│   ├── disaster_summary.json  # Disaster summary statistics
│   ├── disaster_report.html   # HTML statistical report
│   ├── count.csv          # Historical case count statistics
│   └── event/
│       └── 2025danas/     # 2025 Typhoon Danas event archive
└── README.md
```

## Core Scripts

### scripts/01_fetch.php
Fetches disaster data from EMIC API and converts to GeoJSON format.

### scripts/02_summary.php  
Analyzes disaster data and generates statistical summaries including:
- Total case count statistics
- Distribution by county
- Statistics by disaster type
- Statistics by processing status
- Casualty statistics
- Daily trend analysis

### scripts/03_scan.php
Analyzes Git commit history to track case count changes in `docs/cases.json` for each commit, outputting to `docs/count.csv`.

## Deployment & Usage

### System Requirements
- PHP 7.4+
- Git
- Web Server (Apache/Nginx)

### Setting up Cron Jobs
```bash
# Update data every 10 minutes
*/10 * * * * php /path/to/scripts/cron.php
```

### Local Development
```bash
# Manual data fetch
php scripts/01_fetch.php

# Generate summary report
php scripts/02_summary.php

# Analyze historical data
php scripts/03_scan.php
```

## Historical Event Archives

The project includes complete records of major disaster events:

### 2025 Typhoon Danas
- Path: `docs/event/2025danas/`
- Data timestamp: 2025-07-26 03:31
- Includes complete disaster map interface and case data

## Data Sources

- [EMIC Emergency Medical Information Center](https://portal2.emic.gov.tw/)
- Taiwan Disaster Response System

## Technical Highlights

- **Chinese Date Parsing**: Supports Traditional Chinese AM/PM format datetime parsing
- **Performance Optimization**: Migrated from heavy Chart.js to lightweight CSS charts
- **Multi-language Support**: Complete Traditional Chinese interface
- **Responsive Design**: Supports both desktop and mobile devices

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

[Finjon Kiang](https://www.facebook.com/k.olc.tw/) - Tainan City Councilor Candidate for North-Central-West District

## Contributing

Feel free to submit Issues or Pull Requests to improve this project.

---

**Note**: This project is for informational display purposes only. Please refer to official sources for actual disaster information.