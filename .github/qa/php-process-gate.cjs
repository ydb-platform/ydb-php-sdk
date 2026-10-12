const path = require('node:path')
const { prepareK8S } = require(path.resolve(process.argv[2], 'lib/callExecutables.js'))
const { runWorkload } = require(path.resolve(process.argv[2], 'lib/workload.js'))

prepareK8S('')
runWorkload(process.argv[3], {
  id: 'php-final-error',
  dockerPath: 'localhost:5000/ydb-app-current',
  args: '--time 60 --shutdown-time 30 --table-name php_final_error --initial-data-count 1000 --read-rps 1000 --write-rps 100 --prom-pgw http://prometheus-pushgateway:9091',
  timeoutMins: 5
}).catch(error => {
  console.error(error)
  process.exitCode = 1
})
