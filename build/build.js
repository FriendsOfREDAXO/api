// build/build.js
const fs = require('fs');
const path = require('path');

// Create necessary directories
const createDirs = () => {
  const dirs = [
    'assets/vendor/swagger-ui/css',
    'assets/vendor/swagger-ui/js',
    'assets/vendor/swagger-ui/license'
  ];
  
  dirs.forEach(dir => {
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
      console.log(`Created directory: ${dir}`);
    }
  });
};

// Copy files from node_modules to assets directory
const copyFiles = () => {
  const files = [
    {
      src: 'node_modules/swagger-ui-dist/swagger-ui.css',
      dest: 'assets/vendor/swagger-ui/css/swagger-ui.css'
    },
    {
      src: 'node_modules/swagger-ui-dist/swagger-ui-bundle.js',
      dest: 'assets/vendor/swagger-ui/js/swagger-ui-bundle.js'
    },
    {
      src: 'node_modules/swagger-ui-dist/LICENSE',
      dest: 'assets/vendor/swagger-ui/license/LICENSE'
    }
  ];
  
  files.forEach(file => {
    try {
      fs.copyFileSync(file.src, file.dest);
      console.log(`Copied ${file.src} to ${file.dest}`);
    } catch (error) {
      console.error(`Error copying ${file.src}: ${error.message}`);
      
      // Alternative license locations
      if (file.src.includes('LICENSE')) {
        try {
          // Try to find license in parent swagger-ui package if it's not in swagger-ui-dist
          const altLicensePath = 'node_modules/swagger-ui/LICENSE';
          if (fs.existsSync(altLicensePath)) {
            fs.copyFileSync(altLicensePath, file.dest);
            console.log(`Copied alternative license from ${altLicensePath} to ${file.dest}`);
          } else {
            // Create a license file with information about Swagger UI license
            const licenseContent = `Swagger UI is licensed under the Apache License 2.0.
            
For more information, please visit:
https://github.com/swagger-api/swagger-ui/blob/master/LICENSE
            
The full text of the Apache License 2.0 can be found at:
http://www.apache.org/licenses/LICENSE-2.0`;
            
            fs.writeFileSync(file.dest, licenseContent);
            console.log(`Created basic license information at ${file.dest}`);
          }
        } catch (licenseError) {
          console.error(`Could not create license file: ${licenseError.message}`);
        }
      }
    }
  });

  // Create a README file in the vendor directory with attribution
  const readmePath = 'assets/vendor/swagger-ui/README.md';
  const readmeContent = `# Swagger UI

Diese Dateien wurden von der swagger-ui-dist NPM-Paket automatisch kopiert.

- Version: ${getSwaggerUIVersion()}
- Quelle: https://github.com/swagger-api/swagger-ui
- Lizenz: Apache License 2.0
- Datum der Installation: ${new Date().toISOString().split('T')[0]}

Weitere Informationen finden Sie im Lizenzverzeichnis.`;

  fs.writeFileSync(readmePath, readmeContent);
  console.log(`Created README at ${readmePath}`);
};

// Get the installed version of swagger-ui-dist
const getSwaggerUIVersion = () => {
  try {
    const packageJson = JSON.parse(fs.readFileSync('node_modules/swagger-ui-dist/package.json', 'utf8'));
    return packageJson.version || 'unknown';
  } catch (error) {
    return 'unknown';
  }
};

// Main function
const main = () => {
  createDirs();
  copyFiles();
  console.log('Swagger UI assets have been successfully copied to the assets/vendor directory.');
};

// Run the script
main();