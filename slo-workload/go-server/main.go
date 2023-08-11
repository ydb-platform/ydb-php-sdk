package main

import (
	"net/http"
	"net/url"
	"os"
	"strconv"
	"sync"
	"time"
)

func main() {

	var m *Metrics
	var spans = sync.Map{}
	//var spans = map[SpanName]map[int]Span{}
	//spans["read"] = map[int]Span{} /**/
	http.HandleFunc("/prepare", func(writer http.ResponseWriter, request *http.Request) {
		endpoint, err := url.Parse(request.URL.Query().Get("endpoint"))
		if err != nil {
			println("Error in prepare in parse endpoint")
		}
		version := request.URL.Query().Get("version")
		m, err = New(endpoint.String(), version, "workload-php")
		if err != nil {
			println("Error in prepare in create metrics")
		}
		interval, err := strconv.Atoi(request.URL.Query().Get("interval"))
		if err != nil {
			println("Error in prepare in parse interval")
		}
		workTime, err := strconv.Atoi(request.URL.Query().Get("time"))
		if err != nil {
			println("Error in prepare in parse time")
		}
		pushInterval := interval * int(time.Millisecond)
		writer.Write([]byte(request.URL.Query().Encode()))
		go pushGate(m, workTime, pushInterval)
	})
	http.HandleFunc("/start", func(writer http.ResponseWriter, request *http.Request) {
		//println("start", request.URL.Query().Encode())
		job := request.URL.Query().Get("job")
		process := request.URL.Query().Get("process")
		var j SpanName
		if request.URL.Query().Get("job") == "read" {
			j = JobRead
		} else {
			j = JobWrite
		}
		spans.Store(job+process, m.Start(j))
		writer.Write([]byte(request.URL.Query().Encode()))
	})
	http.HandleFunc("/done", func(writer http.ResponseWriter, request *http.Request) {
		//println("done", request.URL.Query().Encode())
		job := request.URL.Query().Get("job")
		process := request.URL.Query().Get("process")
		attempts, err := strconv.Atoi(request.URL.Query().Get("attempts"))
		if err != nil {
			println("Error in done in parse attempts")
		}
		s, ok := spans.Load(job + process)
		if !ok {
			println("Error in done in find span")
		}
		s.(Span).Stop("", attempts)
		//delete(spans[job], process)
		writer.Write([]byte(request.URL.Query().Encode()))
	})
	http.HandleFunc("/fail", func(writer http.ResponseWriter, request *http.Request) {
		//println("fail", request.URL.Query().Encode())
		job := request.URL.Query().Get("job")
		process := request.URL.Query().Get("process")
		error := request.URL.Query().Get("error")
		attempts, err := strconv.Atoi(request.URL.Query().Get("attempts"))
		if err != nil {
			println("Error in done in parse attempts")
		}
		s, ok := spans.Load(job + process)
		if !ok {
			println("Error in done in find span")
		}
		s.(Span).Stop(error, attempts)
		//delete(spans[job], process)
		writer.Write([]byte(request.URL.Query().Encode()))
	})
	err := http.ListenAndServe(":88", nil)
	if err != nil {
		println(err.Error())
		return
	} else {
		println("Server started")
	}
}

func pushGate(m *Metrics, workTime int, interval int) {
	timer := time.NewTimer(time.Duration(1e9 * workTime))
	finish := false
	for !finish {
		select {
		case <-timer.C:
			finish = true
			m.Reset()
			os.Exit(0)
			break
		default:
			time.Sleep(time.Duration(interval))
			m.Push()
		}
	}
}
