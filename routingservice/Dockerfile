FROM python:3.10-slim-buster

RUN apt-get update && apt-get install -y curl

# Tạo các thư mục cần thiết và thiết lập quyền
RUN mkdir -p /home/app/
RUN mkdir -p /var/log/flask-app && touch /var/log/flask-app/flask-app.err.log && touch /var/log/flask-app/flask-app.out.log

# Đặt thư mục làm việc
WORKDIR /home/app

# Sao chép tất cả các tệp vào container
COPY . .

RUN pip install -r requirements.txt

EXPOSE 5000

CMD ["python", "-m", "gunicorn", "--timeout", "900", "-w", "5", "-b", "0.0.0.0:5000", "wsgi:app"]