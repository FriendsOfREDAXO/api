const fs = require('fs');
const path = require('path');

// Create necessary directories
const createDirs = () => {
  const dirs = [
    'assets/vendor/swagger-ui/css',
    'assets/vendor/swagger-ui/js'
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
    }
  ];
  
  files.forEach(file => {
    fs.copyFileSync(file.src, file.dest);
    console.log(`Copied ${file.src} to ${file.dest}`);
  });
};

// Main function
const main = () => {
  createDirs();
  copyFiles();
  console.log('Swagger UI assets have been successfully copied to the assets/vendor directory.');
};

// Run the script
main();