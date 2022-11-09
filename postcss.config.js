module.exports = {
    plugins: [
        require("postcss-import"),
        require("postcss-preset-env")({ stage: 1 }), // stage 1 should work on modern navigators
        require("cssnano")({
            preset: "default",
        }),
    ],
};

`POSTCSS in short:
npm init -y;
npm i -D postcss postcss-cli postcss-import postcss-preset-env cssnano;
create postcss.config.js like this one
in style.css, @import each css files
package.json:
    "scripts":{
        "postcss:watch": "postcss src/style.css --dir dist --watch"
    }
npm run postcss:watch
`;
`PACKAGE.JSON:
{
  "name": "helpdesk",
  "version": "1.0.0",
  "description": "",
  "main": "index.js",
  "scripts": {
    "postcss:watch": "postcss src/public/assets/style.css -d build --watch"
  },
  "repository": {
    "type": "git",
    "url": "git+ssh://git@gitlab.com/grokadok/hpdsk.git"
  },
  "keywords": [],
  "author": "",
  "license": "ISC",
  "bugs": {
    "url": "https://gitlab.com/grokadok/hpdsk/issues"
  },
  "homepage": "https://gitlab.com/grokadok/hpdsk#readme",
  "devDependencies": {
    "cssnano": "^5.0.16",
    "postcss": "^8.4.5",
    "postcss-cli": "^9.1.0",
    "postcss-import": "^14.0.2",
    "postcss-preset-env": "^7.2.3"
  }
}`;
