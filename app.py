import os
from flask import Flask, render_template, request, redirect, url_for, flash
from flask_sqlalchemy import SQLAlchemy

app = Flask(__name__)
app.config['SQLALCHEMY_DATABASE_URI'] = 'sqlite:///school.db'
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False
app.config['SECRET_KEY'] = os.environ.get('SECRET_KEY', os.urandom(24))

db = SQLAlchemy(app)


# Models
class Student(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(100), nullable=False)
    student_id = db.Column(db.String(20), unique=True, nullable=False)
    gender = db.Column(db.String(10))
    age = db.Column(db.Integer)
    grades = db.relationship('Grade', backref='student', lazy=True, cascade='all, delete-orphan')


class Teacher(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(100), nullable=False)
    teacher_id = db.Column(db.String(20), unique=True, nullable=False)
    gender = db.Column(db.String(10))
    subject = db.Column(db.String(100))
    courses = db.relationship('Course', backref='teacher', lazy=True)


class Course(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(100), nullable=False)
    course_id = db.Column(db.String(20), unique=True, nullable=False)
    teacher_id = db.Column(db.Integer, db.ForeignKey('teacher.id'))
    grades = db.relationship('Grade', backref='course', lazy=True, cascade='all, delete-orphan')


class Grade(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    student_id = db.Column(db.Integer, db.ForeignKey('student.id'), nullable=False)
    course_id = db.Column(db.Integer, db.ForeignKey('course.id'), nullable=False)
    score = db.Column(db.Float)


# Home
@app.route('/')
def index():
    student_count = Student.query.count()
    teacher_count = Teacher.query.count()
    course_count = Course.query.count()
    grade_count = Grade.query.count()
    return render_template('index.html',
                           student_count=student_count,
                           teacher_count=teacher_count,
                           course_count=course_count,
                           grade_count=grade_count)


# Student routes
@app.route('/students')
def students():
    all_students = Student.query.all()
    return render_template('students/list.html', students=all_students)


@app.route('/students/add', methods=['GET', 'POST'])
def add_student():
    if request.method == 'POST':
        name = request.form['name'].strip()
        student_id = request.form['student_id'].strip()
        gender = request.form['gender']
        age = request.form.get('age', '').strip()
        if not name or not student_id:
            flash('姓名和学号不能为空', 'danger')
            return render_template('students/add.html')
        if Student.query.filter_by(student_id=student_id).first():
            flash('学号已存在', 'danger')
            return render_template('students/add.html')
        student = Student(name=name, student_id=student_id, gender=gender,
                          age=int(age) if age else None)
        db.session.add(student)
        db.session.commit()
        flash('学生添加成功', 'success')
        return redirect(url_for('students'))
    return render_template('students/add.html')


@app.route('/students/edit/<int:id>', methods=['GET', 'POST'])
def edit_student(id):
    student = db.get_or_404(Student, id)
    if request.method == 'POST':
        name = request.form['name'].strip()
        student_id = request.form['student_id'].strip()
        gender = request.form['gender']
        age = request.form.get('age', '').strip()
        if not name or not student_id:
            flash('姓名和学号不能为空', 'danger')
            return render_template('students/edit.html', student=student)
        existing = Student.query.filter_by(student_id=student_id).first()
        if existing and existing.id != id:
            flash('学号已存在', 'danger')
            return render_template('students/edit.html', student=student)
        student.name = name
        student.student_id = student_id
        student.gender = gender
        student.age = int(age) if age else None
        db.session.commit()
        flash('学生信息更新成功', 'success')
        return redirect(url_for('students'))
    return render_template('students/edit.html', student=student)


@app.route('/students/delete/<int:id>', methods=['POST'])
def delete_student(id):
    student = db.get_or_404(Student, id)
    db.session.delete(student)
    db.session.commit()
    flash('学生删除成功', 'success')
    return redirect(url_for('students'))


# Teacher routes
@app.route('/teachers')
def teachers():
    all_teachers = Teacher.query.all()
    return render_template('teachers/list.html', teachers=all_teachers)


@app.route('/teachers/add', methods=['GET', 'POST'])
def add_teacher():
    if request.method == 'POST':
        name = request.form['name'].strip()
        teacher_id = request.form['teacher_id'].strip()
        gender = request.form['gender']
        subject = request.form.get('subject', '').strip()
        if not name or not teacher_id:
            flash('姓名和工号不能为空', 'danger')
            return render_template('teachers/add.html')
        if Teacher.query.filter_by(teacher_id=teacher_id).first():
            flash('工号已存在', 'danger')
            return render_template('teachers/add.html')
        teacher = Teacher(name=name, teacher_id=teacher_id, gender=gender, subject=subject)
        db.session.add(teacher)
        db.session.commit()
        flash('教师添加成功', 'success')
        return redirect(url_for('teachers'))
    return render_template('teachers/add.html')


@app.route('/teachers/edit/<int:id>', methods=['GET', 'POST'])
def edit_teacher(id):
    teacher = db.get_or_404(Teacher, id)
    if request.method == 'POST':
        name = request.form['name'].strip()
        teacher_id = request.form['teacher_id'].strip()
        gender = request.form['gender']
        subject = request.form.get('subject', '').strip()
        if not name or not teacher_id:
            flash('姓名和工号不能为空', 'danger')
            return render_template('teachers/edit.html', teacher=teacher)
        existing = Teacher.query.filter_by(teacher_id=teacher_id).first()
        if existing and existing.id != id:
            flash('工号已存在', 'danger')
            return render_template('teachers/edit.html', teacher=teacher)
        teacher.name = name
        teacher.teacher_id = teacher_id
        teacher.gender = gender
        teacher.subject = subject
        db.session.commit()
        flash('教师信息更新成功', 'success')
        return redirect(url_for('teachers'))
    return render_template('teachers/edit.html', teacher=teacher)


@app.route('/teachers/delete/<int:id>', methods=['POST'])
def delete_teacher(id):
    teacher = db.get_or_404(Teacher, id)
    db.session.delete(teacher)
    db.session.commit()
    flash('教师删除成功', 'success')
    return redirect(url_for('teachers'))


# Course routes
@app.route('/courses')
def courses():
    all_courses = Course.query.all()
    return render_template('courses/list.html', courses=all_courses)


@app.route('/courses/add', methods=['GET', 'POST'])
def add_course():
    all_teachers = Teacher.query.all()
    if request.method == 'POST':
        name = request.form['name'].strip()
        course_id = request.form['course_id'].strip()
        teacher_id = request.form.get('teacher_id') or None
        if not name or not course_id:
            flash('课程名称和课程号不能为空', 'danger')
            return render_template('courses/add.html', teachers=all_teachers)
        if Course.query.filter_by(course_id=course_id).first():
            flash('课程号已存在', 'danger')
            return render_template('courses/add.html', teachers=all_teachers)
        course = Course(name=name, course_id=course_id,
                        teacher_id=int(teacher_id) if teacher_id else None)
        db.session.add(course)
        db.session.commit()
        flash('课程添加成功', 'success')
        return redirect(url_for('courses'))
    return render_template('courses/add.html', teachers=all_teachers)


@app.route('/courses/edit/<int:id>', methods=['GET', 'POST'])
def edit_course(id):
    course = db.get_or_404(Course, id)
    all_teachers = Teacher.query.all()
    if request.method == 'POST':
        name = request.form['name'].strip()
        course_id = request.form['course_id'].strip()
        teacher_id = request.form.get('teacher_id') or None
        if not name or not course_id:
            flash('课程名称和课程号不能为空', 'danger')
            return render_template('courses/edit.html', course=course, teachers=all_teachers)
        existing = Course.query.filter_by(course_id=course_id).first()
        if existing and existing.id != id:
            flash('课程号已存在', 'danger')
            return render_template('courses/edit.html', course=course, teachers=all_teachers)
        course.name = name
        course.course_id = course_id
        course.teacher_id = int(teacher_id) if teacher_id else None
        db.session.commit()
        flash('课程信息更新成功', 'success')
        return redirect(url_for('courses'))
    return render_template('courses/edit.html', course=course, teachers=all_teachers)


@app.route('/courses/delete/<int:id>', methods=['POST'])
def delete_course(id):
    course = db.get_or_404(Course, id)
    db.session.delete(course)
    db.session.commit()
    flash('课程删除成功', 'success')
    return redirect(url_for('courses'))


# Grade routes
@app.route('/grades')
def grades():
    all_grades = Grade.query.options(
        db.joinedload(Grade.student), db.joinedload(Grade.course)
    ).all()
    return render_template('grades/list.html', grades=all_grades)


@app.route('/grades/add', methods=['GET', 'POST'])
def add_grade():
    all_students = Student.query.all()
    all_courses = Course.query.all()
    if request.method == 'POST':
        student_id = request.form.get('student_id')
        course_id = request.form.get('course_id')
        score = request.form.get('score', '').strip()
        if not student_id or not course_id:
            flash('请选择学生和课程', 'danger')
            return render_template('grades/add.html', students=all_students, courses=all_courses)
        try:
            score_val = float(score) if score else None
            if score_val is not None and not (0 <= score_val <= 100):
                raise ValueError
        except ValueError:
            flash('成绩必须在0到100之间', 'danger')
            return render_template('grades/add.html', students=all_students, courses=all_courses)
        grade = Grade(student_id=int(student_id), course_id=int(course_id), score=score_val)
        db.session.add(grade)
        db.session.commit()
        flash('成绩添加成功', 'success')
        return redirect(url_for('grades'))
    return render_template('grades/add.html', students=all_students, courses=all_courses)


@app.route('/grades/edit/<int:id>', methods=['GET', 'POST'])
def edit_grade(id):
    grade = db.get_or_404(Grade, id)
    all_students = Student.query.all()
    all_courses = Course.query.all()
    if request.method == 'POST':
        student_id = request.form.get('student_id')
        course_id = request.form.get('course_id')
        score = request.form.get('score', '').strip()
        if not student_id or not course_id:
            flash('请选择学生和课程', 'danger')
            return render_template('grades/edit.html', grade=grade,
                                   students=all_students, courses=all_courses)
        try:
            score_val = float(score) if score else None
            if score_val is not None and not (0 <= score_val <= 100):
                raise ValueError
        except ValueError:
            flash('成绩必须在0到100之间', 'danger')
            return render_template('grades/edit.html', grade=grade,
                                   students=all_students, courses=all_courses)
        grade.student_id = int(student_id)
        grade.course_id = int(course_id)
        grade.score = score_val
        db.session.commit()
        flash('成绩更新成功', 'success')
        return redirect(url_for('grades'))
    return render_template('grades/edit.html', grade=grade,
                           students=all_students, courses=all_courses)


@app.route('/grades/delete/<int:id>', methods=['POST'])
def delete_grade(id):
    grade = db.get_or_404(Grade, id)
    db.session.delete(grade)
    db.session.commit()
    flash('成绩删除成功', 'success')
    return redirect(url_for('grades'))


if __name__ == '__main__':
    with app.app_context():
        db.create_all()
    app.run(debug=os.environ.get('FLASK_DEBUG', 'false').lower() == 'true')
