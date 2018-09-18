FROM python:3.6.6

RUN apt-get update && \
    apt-get install -y git make gcc libssl-dev libgmp-dev python-dev libxml2-dev libxslt1-dev zlib1g-dev

RUN git clone https://github.com/dpays/dpaygo.git && \
    cd dpaygo && \
    make install

RUN pip3 install pymongo scrypt autobahn

RUN pip3 install twisted

COPY . /src

CMD ["python", "/src/live.py"]
